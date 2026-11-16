<?php

declare(strict_types=1);

namespace App\Filament\Shared\Forms;

use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Contracts\Support\Htmlable;

final class TranslatableTabs
{
    /**
     * @param  Closure(string): array<Component|Action|ActionGroup|string|Htmlable>  $schemaForLocale
     */
    public static function make(Closure $schemaForLocale): Tabs
    {
        return Tabs::make()->tabs([
            Tab::make('AR')->schema($schemaForLocale('ar')),
            Tab::make('EN')->schema($schemaForLocale('en')),
        ]);
    }
}
