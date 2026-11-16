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
    public static function make(?string $name = null): static
    {
        return parent::make($name)->formatStateUsing(static function (mixed $state): ?string {
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

            return LocalizedDate::format($instant, $market, app()->getLocale(), timezone: $timezone);
        });
    }
}
