<?php

declare(strict_types=1);

namespace App\Filament\Shared\Tables;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Str;
use UnitEnum;

final class StatusBadgeColumn extends TextColumn
{
    /**
     * @param  array<string, string|array<string>>  $colors  Explicit state-to-color overrides.
     */
    public static function make(?string $name = null, array $colors = []): static
    {
        return parent::make($name)
            ->badge()
            ->formatStateUsing(static function (mixed $state): mixed {
                if ($state instanceof HasLabel && ($label = $state->getLabel()) !== null) {
                    return $label;
                }

                if ($state instanceof BackedEnum) {
                    return Str::headline((string) $state->value);
                }

                if ($state instanceof UnitEnum) {
                    return Str::headline($state->name);
                }

                return is_string($state) ? Str::headline($state) : $state;
            })
            ->color(static function (mixed $state) use ($colors): string|array {
                $value = match (true) {
                    $state instanceof BackedEnum => (string) $state->value,
                    $state instanceof UnitEnum => $state->name,
                    is_string($state) => $state,
                    default => null,
                };

                if ($value !== null && isset($colors[$value])) {
                    return $colors[$value];
                }

                return ($state instanceof HasColor ? $state->getColor() : null) ?? 'gray';
            });
    }
}
