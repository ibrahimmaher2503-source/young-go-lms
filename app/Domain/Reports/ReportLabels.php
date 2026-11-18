<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use Illuminate\Support\Facades\Lang;

/** Small value helpers shared by the report queries: translated JSON titles, status words and money in major units. */
final class ReportLabels
{
    /** A spatie-translatable JSON column read through the query builder: current locale, then the other one. */
    public static function translated(?string $json, string $fallback = ''): string
    {
        if ($json === null || $json === '') {
            return $fallback;
        }

        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            return $json;
        }

        foreach ([app()->getLocale(), 'ar', 'en'] as $locale) {
            $value = $decoded[$locale] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return $fallback;
    }

    /** A status code in words; an unknown code stays as it is. */
    public static function status(?string $code): string
    {
        if ($code === null || $code === '') {
            return '';
        }

        $key = 'reports.values.'.$code;

        return Lang::has($key) ? (string) __($key) : $code;
    }

    public static function major(int|float|string|null $minor): float
    {
        return round(((int) $minor) / 100, 2);
    }

    /** The one on-screen report date format (d/m/Y, Latin digits in AR and EN). Exports keep ISO. Anything not a date is returned as is. */
    public static function display(?string $value, bool $withTime = false): string
    {
        if ($value === null || preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}:\d{2}))?/', $value, $m) !== 1) {
            return (string) $value;
        }

        return $m[3].'/'.$m[2].'/'.$m[1].($withTime && isset($m[4]) ? ' '.$m[4] : '');
    }

    public static function datetime(?string $value): string
    {
        return $value === null ? '' : substr($value, 0, 19);
    }
}
