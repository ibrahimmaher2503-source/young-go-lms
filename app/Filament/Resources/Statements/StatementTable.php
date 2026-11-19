<?php

declare(strict_types=1);

namespace App\Filament\Resources\Statements;

use App\Domain\InstructorFinance\InstructorStatement;
use App\Filament\Resources\Refunds\RefundResource;
use App\Filament\Shared\Tables\LocalizedDateColumn;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;

/** Shared columns and PDF action of the statements tables: finance (all instructors) and the instructor's own read-only page. */
final class StatementTable
{
    /** @return list<TextColumn> */
    public static function columns(bool $withInstructor): array
    {
        $money = static fn (int $minor, InstructorStatement $s): string => RefundResource::money($minor, $s->currency);
        $columns = [
            TextColumn::make('number')->label(__('settlements.number'))->fontFamily('mono')->searchable(),
            TextColumn::make('period')->visibleFrom('md')->label(__('settlements.period'))->sortable(),
        ];
        if ($withInstructor) {
            $columns[] = TextColumn::make('instructor')->visibleFrom('md')->label(__('ledger.instructor'))->state(static fn (InstructorStatement $s): string => (string) $s->instructor?->getAttribute('display_name'));
        }

        return [...$columns,
            TextColumn::make('entry_count')->visibleFrom('md')->label(__('settlements.entries')),
            TextColumn::make('earnings_minor')->visibleFrom('md')->label(__('settlements.earnings'))->state(static fn (InstructorStatement $s): string => $money($s->earnings_minor, $s))->extraAttributes(['dir' => 'ltr']),
            TextColumn::make('reversals_minor')->visibleFrom('md')->label(__('settlements.reversals'))->state(static fn (InstructorStatement $s): string => $money($s->reversals_minor, $s))->extraAttributes(['dir' => 'ltr']),
            TextColumn::make('adjustments_minor')->visibleFrom('md')->label(__('settlements.adjustments'))->state(static fn (InstructorStatement $s): string => $money($s->adjustments_minor, $s))->extraAttributes(['dir' => 'ltr']),
            TextColumn::make('net_minor')->label(__('settlements.net'))->state(static fn (InstructorStatement $s): string => $money($s->net_minor, $s))->extraAttributes(['dir' => 'ltr']),
            TextColumn::make('closing_balance_minor')->visibleFrom('md')->label(__('settlements.closing'))->badge()->color(static fn (InstructorStatement $s): string => $s->closing_balance_minor < 0 ? 'danger' : 'gray')
                ->state(static fn (InstructorStatement $s): string => $money($s->closing_balance_minor, $s))->tooltip(static fn (InstructorStatement $s): ?string => $s->closing_balance_minor < 0 ? __('settlements.carried_forward_tip') : null),
            LocalizedDateColumn::make('generated_at')->label(__('settlements.generated'))->sortable()->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    public static function downloadAction(): Action
    {
        return Action::make('pdf')->label(__('settlements.download_pdf'))->icon('heroicon-o-arrow-down-tray')
            ->url(static fn (InstructorStatement $s): string => route('statements.pdf', $s))->openUrlInNewTab();
    }
}
