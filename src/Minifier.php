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

        // 3️⃣ Minify the HTML itself
        $htmlMin = new HtmlMin();
        $htmlMin
            ->doRemoveComments(true)
            ->doRemoveSpacesBetweenTags(true)
            ->doOptimizeAttributes(true)
            ->doRemoveWhitespaceAroundTags(true)
            ->doMakeSameDomainsLinksRelative([]);

        $content = $htmlMin->minify($content);

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