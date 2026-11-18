<?php

declare(strict_types=1);

namespace App\Filament\Resources\LedgerEntries;

use App\Domain\InstructorFinance\LedgerEntry;
use App\Domain\InstructorFinance\LedgerEntryType;
use App\Domain\InstructorFinance\LedgerStatus;
use App\Domain\InstructorFinance\SaleType;
use App\Filament\Resources\Refunds\RefundResource;
use App\Filament\Shared\Tables\LocalizedDateColumn;
use App\Filament\Shared\Tables\StatusBadgeColumn;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

/** Shared columns and filters of the ledger table: the staff view (all instructors) and the instructor's own earnings. */
final class LedgerTable
{
    /** @return list<TextColumn> */
    public static function columns(bool $withInstructor): array
    {
        $columns = [LocalizedDateColumn::make('earned_at')->label(__('ledger.col_date'))->sortable()];
        if ($withInstructor) {
            $columns[] = TextColumn::make('instructor')->label(__('ledger.instructor'))->state(static fn (LedgerEntry $e): string => (string) $e->instructor?->getAttribute('name'));
        }

        return [...$columns,
            TextColumn::make('course')->label(__('ledger.course'))->state(static fn (LedgerEntry $e): string => $e->course === null ? '-' : (string) $e->course->title)->limit(40),
            TextColumn::make('type')->label(__('ledger.type'))->badge()->state(static fn (LedgerEntry $e): string => __('ledger.type_'.$e->type->value)),
            TextColumn::make('sale_type')->label(__('ledger.sale_type'))->state(static fn (LedgerEntry $e): string => $e->sale_type === null ? '-' : __('ledger.sale_'.$e->sale_type->value)),
            TextColumn::make('order.reference')->label(__('ledger.order'))->fontFamily('mono')->placeholder('-'),
            TextColumn::make('base_minor')->label(__('ledger.base'))->state(static fn (LedgerEntry $e): string => $e->base_minor === null ? '-' : RefundResource::money($e->base_minor, $e->currency)),
            TextColumn::make('rate')->label(__('ledger.rate'))->state(static fn (LedgerEntry $e): string => match (true) {
                $e->fixed_amount_minor !== null => RefundResource::money($e->fixed_amount_minor, $e->currency),
                $e->rate_bps !== null => rtrim(rtrim(number_format($e->rate_bps / 100, 2, '.', ''), '0'), '.').'%',
                default => '-',
            }),
            TextColumn::make('amount_minor')->label(__('ledger.amount'))->state(static fn (LedgerEntry $e): string => RefundResource::money($e->amount_minor, $e->currency))->extraAttributes(['dir' => 'ltr']),
            StatusBadgeColumn::make('status', ['accrued' => 'info', 'pending' => 'warning', 'approved' => 'success', 'held' => 'danger', 'paid' => 'success', 'reversed' => 'gray'])->label(__('ledger.status'))
                ->formatStateUsing(static fn (mixed $state): string => __('ledger.status_'.($state instanceof LedgerStatus ? $state->value : (string) $state))),
            TextColumn::make('reason')->label(__('ledger.reason'))->limit(40)->tooltip(static fn (LedgerEntry $e): ?string => $e->reason)->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    /** @return list<SelectFilter|Filter> */
    public static function filters(): array
    {
        return [
            SelectFilter::make('type')->label(__('ledger.type'))->options(collect(LedgerEntryType::cases())->mapWithKeys(static fn (LedgerEntryType $t): array => [$t->value => __('ledger.type_'.$t->value)])->all()),
            SelectFilter::make('sale_type')->label(__('ledger.sale_type'))->options(collect(SaleType::cases())->mapWithKeys(static fn (SaleType $t): array => [$t->value => __('ledger.sale_'.$t->value)])->all()),
            SelectFilter::make('status')->label(__('ledger.status'))->options(collect(LedgerStatus::cases())->mapWithKeys(static fn (LedgerStatus $s): array => [$s->value => __('ledger.status_'.$s->value)])->all()),
            Filter::make('period')->label(__('ledger.period'))->schema([
                DatePicker::make('from')->label(__('ledger.from')),
                DatePicker::make('until')->label(__('ledger.until')),
            ])->query(static function (Builder $query, array $data): Builder {
                if (filled($data['from'] ?? null)) {
                    $query->whereDate('earned_at', '>=', $data['from']);
                }
                if (filled($data['until'] ?? null)) {
                    $query->whereDate('earned_at', '<=', $data['until']);
                }

                return $query;
            }),
        ];
    }
}
