<?php

declare(strict_types=1);

namespace App\Domain\Migration\Support;

use Illuminate\Support\Facades\DB;

/**
 * Script-based bilingual name/text detection (Migration Plan §5A) and safe slug generation.
 */
final class ScriptNameMapper
{
    public static function containsArabic(string $value): bool
    {
        return preg_match('/\p{Arabic}/u', $value) === 1;
    }

    public static function detectLocale(string $value, string $fallbackLocale = 'ar'): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return $fallbackLocale;
        }

        if (self::containsArabic($trimmed)) {
            return 'ar';
        }

        if (preg_match('/[A-Za-z]/', $trimmed) === 1) {
            return 'en';
        }

        return $fallbackLocale;
    }

    /**
     * Migration Plan §5A: Arabic letters map to `name_ar` leaving `name_en` null; Latin text maps to `name_en`
     * leaving `name_ar` null so the user can complete the missing script later.
     *
     * @return array{name_ar: ?string, name_en: ?string, display_name: string}
     */
    public static function splitPersonName(string $fullName): array
    {
        $clean = preg_replace('/\s+/u', ' ', trim($fullName)) ?? trim($fullName);
        if ($clean === '') {
            return ['name_ar' => null, 'name_en' => null, 'display_name' => ''];
        }

        if (self::containsArabic($clean)) {
            return ['name_ar' => mb_substr($clean, 0, 255), 'name_en' => null, 'display_name' => mb_substr($clean, 0, 255)];
        }

        return ['name_ar' => null, 'name_en' => mb_substr($clean, 0, 255), 'display_name' => mb_substr($clean, 0, 255)];
    }

    /** @return array<string, string> */
    public static function bilingual(string $text, string $fallbackLocale = 'ar'): array
    {
        $clean = trim($text);
        if ($clean === '') {
            return [];
        }

        return [self::detectLocale($clean, $fallbackLocale) => $clean];
    }

    public static function slugify(string $text, string $fallback = 'item'): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', $text) ?? '';
        $slug = trim($slug, '-');

        if ($slug === '') {
            $slug = $fallback;
        }

        return rtrim(mb_substr($slug, 0, 120, 'UTF-8'), '-') ?: $fallback;
    }

    public static function uniqueSlug(string $table, string $column, string $source, string $fallback, ?int $ignoreId = null): string
    {
        $base = self::slugify($source, $fallback);
        $slug = $base;

        for ($i = 2; ; $i++) {
            $query = DB::table($table)->where($column, $slug);
            if ($ignoreId !== null) {
                $query->where('id', '!=', $ignoreId);
            }

            if (! $query->exists()) {
                return $slug;
            }

            $suffix = '-'.$i;
            $slug = rtrim(mb_substr($base, 0, 120 - strlen($suffix), 'UTF-8'), '-').$suffix;
        }
    }
}
