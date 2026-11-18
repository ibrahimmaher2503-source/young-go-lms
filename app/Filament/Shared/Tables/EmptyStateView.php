<?php

declare(strict_types=1);

namespace App\Filament\Shared\Tables;

use Illuminate\Contracts\View\View;

/** D9: one empty state for staff tables, rendered by the shared `x-ui.empty-state` component (icon, title, hint). */
final class EmptyStateView
{
    public static function make(string $icon, string $titleKey, string $hintKey): View
    {
        return view('filament.shared.empty-state', ['icon' => $icon, 'title' => __($titleKey), 'description' => __($hintKey)]);
    }
}
