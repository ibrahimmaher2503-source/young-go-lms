<?php

declare(strict_types=1);

namespace App\Domain\Migration\Support;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Parses legacy Unix integer timestamps, string dates, durations, and birth dates into UTC values.
 */
final class LegacyDateParser
{
    public static function toUtc(mixed $raw): ?CarbonImmutable
    {
        if ($raw === null || $raw === '' || $raw === 0 || $raw === '0') {
            return null;
        }

        if (is_int($raw) || (is_string($raw) && ctype_digit(trim($raw)))) {
            $timestamp = (int) trim((string) $raw);

            return $timestamp > 0 ? CarbonImmutable::createFromTimestampUTC($timestamp) : null;
        }

        if (is_string($raw)) {
            $trimmed = trim($raw);
            if ($trimmed === '' || $trimmed === '0000-00-00' || $trimmed === '0000-00-00 00:00:00') {
                return null;
            }

            try {
                return CarbonImmutable::parse($trimmed, 'UTC')->utc();
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    public static function durationToSeconds(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_int($raw) || (is_string($raw) && ctype_digit(trim($raw)))) {
            $seconds = (int) trim((string) $raw);

            return $seconds >= 0 ? $seconds : null;
        }

        $value = trim((string) $raw);
        if (preg_match('/^(\d{1,3}):([0-5]?\d):([0-5]?\d)$/', $value, $match) === 1) {
            return ((int) $match[1] * 3600) + ((int) $match[2] * 60) + (int) $match[3];
        }

        if (preg_match('/^(\d{1,3}):([0-5]?\d)$/', $value, $match) === 1) {
            return ((int) $match[1] * 60) + (int) $match[2];
        }

        return null;
    }

    /** @return array{birth_year: ?int, birth_month: ?int} */
    public static function birthYearMonth(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return ['birth_year' => null, 'birth_month' => null];
        }

        $date = self::toUtc($raw);
        if ($date === null) {
            return ['birth_year' => null, 'birth_month' => null];
        }

        return ['birth_year' => $date->year, 'birth_month' => $date->month];
    }
}
