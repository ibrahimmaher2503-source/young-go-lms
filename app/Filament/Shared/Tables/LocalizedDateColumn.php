<?php

declare(strict_types=1);

namespace App\Filament\Shared\Tables;

use App\Domain\Shared\Markets\Market;
use App\Domain\Shared\Time\LocalizedDate;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Filament\Tables\Columns\TextColumn;
use InvalidArgumentException;

final class LocalizedDateColumn extends TextColumn
{
    public static function make(?string $name = null, string $pattern = 'dd/MM/yyyy HH:mm'): static
    {
        return parent::make($name)->extraAttributes(['class' => 'yg-date', 'dir' => 'ltr'])->formatStateUsing(static function (mixed $state) use ($pattern): ?string {
            if ($state === null || $state === '') {
                return null;
            }

            if (! $state instanceof DateTimeInterface && ! is_string($state)) {
                throw new InvalidArgumentException('A date column state must be a date-time or UTC date string.');
            }

            $instant = $state instanceof DateTimeInterface
                ? $state
                : new DateTimeImmutable($state, new DateTimeZone('UTC'));

            $market = null;
            foreach (Market::cases() as $candidate) {
                if ($candidate->isActive()) {
                    $market = $candidate;
                    break;
                }
            }
            $market ??= Market::Egypt;

            $timezone = auth()->user()?->timezone;
            if (! is_string($timezone) || $timezone === '') {
                $timezone = $market->timezone();
            }

            // B1: the cell is an LTR-isolated run (class yg-date), so Arabic month names no longer reorder the digits.
            return LocalizedDate::format($instant, $market, app()->getLocale(), pattern: $pattern, timezone: $timezone);
        });
    }
}
