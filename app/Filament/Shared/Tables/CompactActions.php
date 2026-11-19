<?php

declare(strict_types=1);

namespace App\Filament\Shared\Tables;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Tables\Enums\RecordActionsPosition;

/**
 * P8 fix 5 (T1/W2): row actions live in one ellipsis menu placed first in the row, so they stay on screen at 390 and
 * on wide tables without scrolling. Filament hides the menu for rows where every action is hidden.
 * Usage: `->recordActions(CompactActions::group([...]), CompactActions::POSITION)`.
 */
final class CompactActions
{
    public const RecordActionsPosition POSITION = RecordActionsPosition::BeforeCells;

    /** @param  array<int, Action|ActionGroup>  $actions */
    public static function group(array $actions): ActionGroup
    {
        return ActionGroup::make($actions)->icon('heroicon-m-ellipsis-vertical')->label(__('components.more'))->tooltip(__('components.more'))->color('gray');
    }
}
