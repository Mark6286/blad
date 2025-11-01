<?php
namespace Blad;

use Exception;

/**
 * Blad - Lightweight Blade-like PHP Template Engine
 * -------------------------------------------------
 * Features:
 * - @extends, @section, @yield, @include, @includeWhen
 * - @if, @foreach, @switch, @json, @csrf, @css, @js
 * - Custom directives and globals
 * - File-based compilation cache (optional)
 * - Configurable view path, cache path, file extension
 * - Debug & error logging
 *
 * Author: Reymark Balaod
 * License: MIT
 */
class Blad extends Minifier
{
    protected static string $viewsPath = './resources/views';
    protected static $cachePath = './storage/cache/views'; // string|false
    protected static string $ext = '.blad.php';
    protected static array  $sections = [];
    protected static array  $globals  = [];
    protected static string $layout   = '';
    protected static array  $directives = [];
    protected static bool   $debug = false;

    // ===== Configuration =====

    public static function setPath(string $path): void
    {
        self::$viewsPath = rtrim($path, '/');
    }

    public static function setCachePath($path): void
    {
        if ($path === false) {
            self::$cachePath = false;
            return;
        }
        self::$cachePath = rtrim($path, '/');
        if (!is_dir(self::$cachePath)) mkdir(self::$cachePath, 0777, true);
    }

    public static function setExtension(string $ext): void
    {
        self::$ext = $ext;
    }

    public static function enableDebug(bool $state = true): void
    {
        self::$debug = $state;
    }

    // ===== Globals =====

    public static function setGlobals(array $data): void
    {
        foreach ($data as $key => $value) {
            self::$globals[$key] = is_callable($value) ? $value : fn() => $value;
        }
    }

    public static function updateGlobal(string $key, $value): void
    {
        self::$globals[$key] = is_callable($value) ? $value : fn() => $value;
    }

    protected static function getGlobals(): array
    {
        $evaluated = [];
        foreach (self::$globals as $key => $value) {
            $evaluated[$key] = $value();
        }
        return $evaluated;
    }

    // ===== Rendering =====

    public static function render(string $template, array $data = [], bool $return = false)
    {
        try {
            self::$sections = [];
            self::$layout   = '';

            $data = array_merge(self::getGlobals(), $data);
            $content = self::compile($template, $data);
            $output  = self::$layout ? self::compile(self::$layout, $data) : $content;

            if ($return) return $output;
            echo $output;
        } catch (Exception $e) {
            self::log("Render error in '{$template}': " . $e->getMessage());
            if (self::$debug) {
                echo "<pre style='color:red;'>Blad Error: {$e->getMessage()}</pre>";
            }
        }
    }

    // ===== Compiler Core =====

    protected static function compile(string $template, array $data): string
    {
        $sourcePath = self::$viewsPath . '/' . str_replace('.', '/', $template) . self::$ext;
        if (!file_exists($sourcePath)) {
            throw new Exception("View not found: {$template}");
        }

        // === No cache mode ===
        if (self::$cachePath === false) {
            $raw = file_get_contents($sourcePath);
            $compiled = self::processAll($raw, $data);
            ob_start();
            extract($data);
            eval('?>' . $compiled);
            return ob_get_clean();
        }

        // === Cached mode ===
        $cachePath = self::$cachePath . '/' . sha1($sourcePath) . '.php';
        if (!file_exists($cachePath) || filemtime($sourcePath) > filemtime($cachePath)) {
            $raw = file_get_contents($sourcePath);
            $compiled = self::processAll($raw, $data);
            file_put_contents($cachePath, self::minifyTemplate("<?php use Blad\\Blad; ?>\n?>$compiled"));
        }

        extract($data);
        ob_start();
        include $cachePath;
        return ob_get_clean();
    }

    // ===== Main Processor =====

    protected static function processAll(string $raw, array $data): string
    {
        $raw = self::stripComments($raw);
        $raw = self::handleExtends($raw);
        $raw = self::extractSections($raw);
        $raw = self::compileIncludes($raw, $data);
        $raw = self::compileYields($raw, $data);
        $raw = self::compileControlStructures($raw);
        $raw = self::compileAssets($raw);
        $raw = self::compileJson($raw);
        $raw = self::applyDirectives($raw);
        $raw = self::compileUnescaped($raw);
        $raw = self::compileEscaped($raw);
        return $raw;
    }

    // ===== Compilers =====

    protected static function stripComments(string $raw): string
    {
        return preg_replace('/{{--.*?--}}/s', '', $raw);
    }

    protected static function handleExtends(string $raw): string
    {
        if (preg_match('/@extends\(["\'](.+?)["\']\)/', $raw, $m)) {
            self::$layout = str_replace('.', '/', $m[1]);
            return str_replace($m[0], '', $raw);
        }
        return $raw;
    }

    protected static function extractSections(string $raw): string
    {
        return preg_replace_callback('/@section\(["\'](.+?)["\']\)(.*?)@endsection/s', function ($m) {
            self::$sections[$m[1]] = $m[2];
            return '';
        }, $raw);
    }

    protected static function compileYields(string $raw, array $data): string
    {
        return preg_replace_callback('/@yield\(["\'](.+?)["\']\)/', function ($m) use ($data) {
            $section = self::$sections[$m[1]] ?? '';
            return self::compilePartial($section, $data);
        }, $raw);
    }

    protected static function compileIncludes(string $raw, array $data): string
    {
        $raw = preg_replace_callback('/@include\(["\'](.+?)["\']\)/', function ($m) use ($data) {
            return self::compile($m[1], $data);
        }, $raw);

        $raw = preg_replace_callback('/@includeWhen\((.+?),\s*["\'](.+?)["\']\)/', function ($m) use ($data) {
            return "<?php if ({$m[1]}) echo \\Blad\\Blad::render('{$m[2]}', get_defined_vars(), true); ?>";
        }, $raw);

        return $raw;
    }

    protected static function compilePartial(string $raw, array $data): string
    {
        ob_start();
        extract($data);
        eval('?>' . $raw);
        return ob_get_clean();
    }

    protected static function compileJson(string $raw): string
    {
        return preg_replace('/@json\((.+?)\)/', '<?php echo json_encode($1, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>', $raw);
    }

    protected static function compileAssets(string $raw): string
    {
        $raw = str_replace('@csrf', self::csrfToken(), $raw);

        $raw = preg_replace_callback('/@css\(["\'](.+?)["\']\)/', function ($m) {
            $file = self::$viewsPath . '/../resources/libs/' . $m[1];
            $v = file_exists($file) ? filemtime($file) : time();
            return "<link rel=\"stylesheet\" href=\"/resources/libs/{$m[1]}?v={$v}\">";
        }, $raw);

        $raw = preg_replace_callback('/@js\(["\'](.+?)["\']\)/', function ($m) {
            $file = self::$viewsPath . '/../resources/libs/' . $m[1];
            $v = file_exists($file) ? filemtime($file) : time();
            return "<script src=\"/resources/libs/{$m[1]}?v={$v}\"></script>";
        }, $raw);

        return $raw;
    }

    protected static function compileControlStructures(string $raw): string
    {
        $map = [
            '/@if\s*\((.*?)\)/'        => '<?php if ($1): ?>',
            '/@elseif\s*\((.*?)\)/'    => '<?php elseif ($1): ?>',
            '/@else\b/'                => '<?php else: ?>',
            '/@endif\b/'               => '<?php endif; ?>',
            '/@foreach\s*\((.*?)\)/'   => '<?php foreach ($1): ?>',
            '/@endforeach\b/'          => '<?php endforeach; ?>',
            '/@for\s*\((.*?)\)/'       => '<?php for ($1): ?>',
            '/@endfor\b/'              => '<?php endfor; ?>',
            '/@while\s*\((.*?)\)/'     => '<?php while ($1): ?>',
            '/@endwhile\b/'            => '<?php endwhile; ?>',
            '/@switch\s*\((.*?)\)/'    => '<?php switch($1): ?>',
            '/@case\s*\((.*?)\)/'      => '<?php case $1: ?>',
            '/@break\b/'               => '<?php break; ?>',
            '/@default\b/'             => '<?php default: ?>',
            '/@endswitch\b/'           => '<?php endswitch; ?>',
            '/@php/'                   => '<?php ',
            '/@endphp/'                => '?>'
        ];
        return preg_replace(array_keys($map), array_values($map), $raw);
    }

    protected static function compileEscaped(string $raw): string
    {
        return preg_replace('/{{\s*(.+?)\s*}}/', '<?php echo htmlspecialchars($1 ?? "", ENT_QUOTES, "UTF-8"); ?>', $raw);
    }

    protected static function compileUnescaped(string $raw): string
    {
        return preg_replace('/{!!\s*(.+?)\s*!!}/', '<?php echo $1 ?? ""; ?>', $raw);
    }

    protected static function csrfToken(): string
    {
        if (!isset($_SESSION)) session_start();
        if (!isset($_SESSION['_csrf_token'])) $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['_csrf_token'];
    }

    // ===== Directives =====

    public static function directive(string $name, callable $callback): void
    {
        self::$directives[$name] = $callback;
    }

    protected static function applyDirectives(string $raw): string
    {
        foreach (self::$directives as $name => $callback) {
            $pattern = '/@' . preg_quote($name, '/') . '\((.*?)\)/';
            $raw = preg_replace_callback($pattern, fn($m) => call_user_func($callback, trim($m[1])), $raw);
        }
        return $raw;
    }

    // ===== Logging =====

    protected static function log(string $msg): void
    {
        $logDir = self::$cachePath && is_string(self::$cachePath)
            ? self::$cachePath
            : __DIR__;
        file_put_contents(
            $logDir . '/blad.log',
            '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL,
            FILE_APPEND
        );
    }

    public static function clearCache(): void
    {
        if (self::$cachePath && is_dir(self::$cachePath)) {
            foreach (glob(self::$cachePath . '/*.php') as $file) unlink($file);
        }
    }
}
