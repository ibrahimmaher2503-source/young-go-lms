<?php

declare(strict_types=1);

namespace App\Domain\Reports;

/**
 * CSV/formula-injection guard. A string cell that a spreadsheet could read as a formula (starts with = + - @, a tab or a carriage
 * return, also after leading spaces) is prefixed with an apostrophe so it opens as text. Numbers (int/float) are left alone, so a
 * negative ledger reversal stays a number; money is exported as a number, never as a string.
 */
final class CsvCell
{
    private const string TRIGGERS = "=+-@\t\r";

    public static function safe(string|int|float|bool|null $value): string|int|float
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (! is_string($value)) {
            return $value;
        }

        $first = ltrim($value, " \0\x0B");

        return $first !== '' && str_contains(self::TRIGGERS, $first[0]) ? "'".$value : $value;
    }

    /**
     * @param  list<string|int|float|bool|null>  $row
     * @return list<string|int|float>
     */
    public static function row(array $row): array
    {
        return array_map(static fn (string|int|float|bool|null $cell): string|int|float => self::safe($cell), $row);
    }
}
