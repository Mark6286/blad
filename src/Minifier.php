<?php
namespace Blad;

use MatthiasMullie\Minify;
use voku\helper\HtmlMin;

class Minifier
{
    /**
     * Minify a full HTML template (including inline JS/CSS)
     */
    public static function minifyTemplate(string $content): string
    {
        // Protect PHP and Blade-like tags
        $replacements = [];

        // Protect PHP blocks and Blade tags
        $content = preg_replace_callback(
            '/(<\?(?:php|=)?[\s\S]*?\?>|{{\s*.*?\s*}}|{!!\s*.*?\s*!!}|@\w+)/',
            function ($matches) use (&$replacements) {
                $key                = '__BLADEPHP__' . count($replacements) . '__';
                $replacements[$key] = $matches[1];
                return $key;
            },
            $content
        );

        // 1️⃣ Minify inline JS
        $content = preg_replace_callback('/<script\b[^>]*>([\s\S]*?)<\/script>/i', function ($matches) {
            $minifier = new Minify\JS($matches[1]);
            return '<script>' . $minifier->minify() . '</script>';
        }, $content);

        // 2️⃣ Minify inline CSS
        $content = preg_replace_callback('/<style\b[^>]*>([\s\S]*?)<\/style>/i', function ($matches) {
            $minifier = new Minify\CSS($matches[1]);
            return '<style>' . $minifier->minify() . '</style>';
        }, $content);

        // 3️⃣ Minify HTML (safe)
        $htmlMin = new HtmlMin();
        $htmlMin
            ->doRemoveComments(true)
            ->doRemoveSpacesBetweenTags(true)
            ->doOptimizeAttributes(true)
            ->doRemoveWhitespaceAroundTags(true);

        $content = $htmlMin->minify($content);

        // Restore PHP and Blade code
        $content = strtr($content, $replacements);

        return trim($content);
    }

    /**
     * Minify standalone JS file content
     */
    public static function minifyJS(string $js): string
    {
        $min = new Minify\JS($js);
        return $min->minify();
    }

    /**
     * Minify standalone CSS file content
     */
    public static function minifyCSS(string $css): string
    {
        $min = new Minify\CSS($css);
        return $min->minify();
    }
}
