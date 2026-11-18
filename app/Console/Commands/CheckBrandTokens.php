<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Brand;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Keeps resources/css/tokens.css the only place that defines brand colors and fonts.
 * 1) Scans views, JS, CSS and app code for hardcoded colors / font families / font links.
 * 2) Checks config/brand.php (literal mirrors for PDFs and SVGs) against tokens.css.
 * Add `brand-check-ignore` on a line to exempt it, or list a path in config('brand.check_allow') with a reason.
 */
final class CheckBrandTokens extends Command
{
    protected $signature = 'app:check-brand-tokens';

    protected $description = 'Fail on hardcoded brand colors/fonts outside tokens.css and on config/brand.php drift from tokens.css';

    private const TOKENS = 'resources/css/tokens.css';

    private const PALETTE = 'slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose';

    private const UTILITY = 'text|bg|border|ring|from|to|via|fill|stroke|shadow|divide|outline|decoration|accent|caret';

    public function handle(): int
    {
        $problems = [...$this->scan(), ...$this->parity()];

        foreach ($problems as $problem) {
            $this->error($problem);
        }

        if ($problems === []) {
            $this->info('Brand token check passed.');
        }

        return $problems === [] ? self::SUCCESS : self::FAILURE;
    }

    /** @return list<string> */
    private function scan(): array
    {
        $problems = [];
        $allow = array_keys((array) config('brand.check_allow', []));

        foreach ($this->files() as $file) {
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));
            if ($relative === self::TOKENS || in_array($relative, $allow, true)) {
                continue;
            }

            $filamentScope = str_contains($relative, 'Filament') || str_contains($relative, 'filament');
            foreach (preg_split('/\R/', $file->getContents()) ?: [] as $number => $line) {
                if (str_contains($line, 'brand-check-ignore')) {
                    continue;
                }
                foreach ($this->violations($line, $filamentScope) as $found) {
                    $problems[] = $relative.':'.($number + 1).': '.$found;
                }
            }
        }

        return $problems;
    }

    /** @return list<string> */
    private function violations(string $line, bool $filamentScope): array
    {
        $palette = $filamentScope ? str_replace('gray|', '', self::PALETTE) : self::PALETTE;
        $patterns = [
            '/(?<![\w&])#(?:[0-9a-fA-F]{8}|[0-9a-fA-F]{6}|[0-9a-fA-F]{4}|[0-9a-fA-F]{3})(?![\w-])/',
            '/(?<![\w-])(?:rgba?|hsla?|hwb|oklch|oklab|lab|lch)\(/',
            '/font-family\s*[:=]\s*(?!var\()["\']?[^;"\'\s]/',
            '/@font-face/',
            '/fonts\.(?:googleapis|gstatic|bunny)\.|use\.typekit\.net|use\.fontawesome\.com/',
            '/(?<![\w-])font-\[/',
            '/(?<![\w-])(?:'.self::UTILITY.')-\[(?:#|oklch|rgb|hsl|color:)/',
            '/(?<![\w-])(?:'.self::UTILITY.')-(?:white|black)(?![\w-])/',
            '/(?<![\w-])(?:'.self::UTILITY.')-(?:'.$palette.')-\d{2,3}(?![\w-])/',
            '/(?<![\w-])(?:color|background(?:-color)?|border-color)\s*:\s*(?:white|black|red|green|blue|yellow|orange|purple|gray|grey)\b/',
            '/(?<![\w-])(?:fill|stroke)=["\'](?:white|black|red|green|blue|yellow|orange|purple|gray|grey)["\']/',
            '/->colors\(\s*\[/',
            '/->font\(\s*[\'"]/',
        ];

        $found = [];
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $line, $matches) > 0) {
                foreach ($matches[0] as $match) {
                    $found[] = trim($match);
                }
            }
        }

        return $found;
    }

    /** @return iterable<SplFileInfo> */
    private function files(): iterable
    {
        $roots = [
            resource_path('views') => ['php'],
            resource_path('js') => ['js', 'mjs', 'ts'],
            resource_path('css') => ['css'],
            app_path() => ['php'],
            public_path() => ['svg', 'html', 'css'],
        ];

        foreach ($roots as $root => $extensions) {
            if (! is_dir($root)) {
                continue;
            }
            foreach (File::allFiles($root) as $file) {
                $path = str_replace('\\', '/', $file->getPathname());
                $vendored = $root === public_path() && preg_match('#/public/(?:build|css/filament|js/filament|fonts|vendor)/#', $path) === 1;
                if (! $vendored && in_array(strtolower($file->getExtension()), $extensions, true)) {
                    yield $file;
                }
            }
        }
    }

    /** @return list<string> */
    private function parity(): array
    {
        $path = base_path(self::TOKENS);
        if (! is_file($path)) {
            return [self::TOKENS.' is missing'];
        }
        $css = (string) file_get_contents($path);
        $tokens = $this->tokens($css);
        $problems = [];

        foreach ((array) config('brand.pdf_colors', []) as $name => $color) {
            $expected = $this->resolve($tokens, (string) ($color['token'] ?? ''));
            if ($expected === null) {
                $problems[] = "config/brand.php pdf_colors.{$name}: token ".($color['token'] ?? '?').' not found in tokens.css';
            } elseif ($this->normalize($expected) !== $this->normalize((string) ($color['value'] ?? ''))) {
                $problems[] = "config/brand.php pdf_colors.{$name} drifted: config '".($color['value'] ?? '')."' vs tokens.css '{$expected}'";
            }
        }

        $family = $this->resolve($tokens, (string) config('brand.font.token'));
        $configFamily = (string) config('brand.font.family');
        if ($family === null || trim($family, '"\' ') !== $configFamily) {
            $problems[] = "config/brand.php font.family '{$configFamily}' drifted from tokens.css ".config('brand.font.token')." '{$family}'";
        }

        $stack = $this->resolve($tokens, (string) config('brand.font.stack_token'));
        if ($stack === null || $this->normalize($stack) !== $this->normalize((string) config('brand.font.stack'))) {
            $problems[] = 'config/brand.php font.stack drifted from tokens.css '.config('brand.font.stack_token')." '{$stack}'";
        }

        if (preg_match('/@font-face\s*{[^}]*font-family:\s*"([^"]+)"[^}]*url\("([^"]+)"\)/s', $css, $face) !== 1) {
            $problems[] = 'tokens.css has no @font-face with a url()';
        } else {
            if ($face[1] !== $configFamily) {
                $problems[] = "tokens.css @font-face family '{$face[1]}' differs from config/brand.php font.family '{$configFamily}'";
            }
            $declared = realpath(dirname($path).'/'.$face[2]);
            $configured = realpath(Brand::fontPath());
            if ($declared === false || $configured === false || $declared !== $configured) {
                $problems[] = "tokens.css @font-face file '{$face[2]}' does not resolve to config/brand.php font.file '".config('brand.font.file')."'";
            }
        }

        return $problems;
    }

    /** @return array<string, string> */
    private function tokens(string $css): array
    {
        preg_match_all('/(--yg-[\w-]+)\s*:\s*([^;]+);/', $css, $matches, PREG_SET_ORDER);
        $tokens = [];
        foreach ($matches as $match) {
            $tokens[$match[1]] ??= trim($match[2]); // first (light/:root) definition wins
        }

        return $tokens;
    }

    /** @param array<string, string> $tokens */
    private function resolve(array $tokens, string $name, int $depth = 0): ?string
    {
        $value = $tokens[$name] ?? null;
        if ($value === null || $depth > 8) {
            return $value;
        }

        return preg_replace_callback('/var\((--[\w-]+)\)/', fn (array $m): string => $this->resolve($tokens, $m[1], $depth + 1) ?? $m[0], $value);
    }

    private function normalize(string $value): string
    {
        return strtolower((string) preg_replace('/\s+/', ' ', trim($value)));
    }
}
