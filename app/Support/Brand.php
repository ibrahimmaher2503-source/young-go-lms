<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\File;

/** Reads config/brand.php, which mirrors resources/css/tokens.css (the source of truth). */
final class Brand
{
    public static function fontFamily(): string
    {
        return (string) config('brand.font.family');
    }

    public static function svgFontFamily(): string
    {
        return (string) config('brand.font.svg_fallback');
    }

    public static function fontPath(): string
    {
        return resource_path((string) config('brand.font.file'));
    }

    /** Base64 data URI so a standalone PDF page can embed the font without network access. */
    public static function fontDataUri(): string
    {
        return 'data:font/ttf;base64,'.base64_encode(File::get(self::fontPath()));
    }

    /** CSS custom properties for a PDF `:root` block, with the font-face rule. */
    public static function pdfCss(): string
    {
        $css = '@font-face { font-family: "'.self::fontFamily().'"; src: url("'.self::fontDataUri().'") format("truetype"); font-weight: 100 900; font-style: normal; }'."\n";
        $css .= ':root {';
        foreach ((array) config('brand.pdf_colors') as $name => $color) {
            $css .= ' --'.$name.': '.$color['value'].';';
        }

        return $css.' --font: '.config('brand.font.pdf_stack').'; }';
    }
}
