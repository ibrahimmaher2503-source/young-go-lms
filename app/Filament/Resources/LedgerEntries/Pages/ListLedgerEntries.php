<?php

declare(strict_types=1);

namespace App\Filament\Resources\LedgerEntries\Pages;

use App\Domain\InstructorFinance\Actions\RecordLedgerAdjustment;
use App\Domain\InstructorFinance\Queries\LedgerTotals;
use App\Domain\Instructors\Queries\PublicInstructors;
use App\Domain\Shared\Markets\Market;
use App\Filament\Resources\LedgerEntries\LedgerEntryResource;
use App\Filament\Resources\Refunds\RefundResource;
use App\Filament\Shared\Pages\WorkspaceListRecords;
use App\Models\Course;
use App\Models\User;
use Brick\Math\RoundingMode;
use Brick\Money\Money as BrickMoney;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ListLedgerEntries extends WorkspaceListRecords
{
    protected static string $resource = LedgerEntryResource::class;

    protected function getHeaderActions(): array
    {
        $currency = Market::Egypt->currency();

        return [
            Action::make('adjust')->label(__('ledger.add_adjustment'))->icon('heroicon-o-plus')
                ->visible(static fn (): bool => Gate::allows('settlement.manage'))
                ->modalDescription(__('ledger.adjustment_help'))
                ->schema([
                    Select::make('instructor_id')->label(__('ledger.instructor'))->required()->searchable()->native(false)
                        ->options(static fn (): array => User::query()->role(PublicInstructors::ROLES)->orderBy('name')->limit(300)->get()->mapWithKeys(static fn (User $u): array => [$u->getKey() => (string) $u->display_name])->all()),
                    Select::make('course_id')->label(__('ledger.course'))->searchable()->native(false)
                        ->options(static fn (): array => Course::query()->orderByDesc('id')->limit(300)->get()->mapWithKeys(static fn (Course $c): array => [$c->getKey() => (string) $c->title])->all()),
                    TextInput::make('amount')->label(__('ledger.signed_amount', ['currency' => RefundResource::currencyLabel($currency)]))->numeric()->required()->helperText(__('ledger.signed_amount_help')),
                    Textarea::make('reason')->label(__('ledger.reason'))->required()->minLength(5)->maxLength(500),
                ])
                ->action(static function (array $data, Action $action) use ($currency): void {
                    $actor = Auth::user();
                    abort_unless($actor instanceof User, 401);
                    try {
                        $minor = BrickMoney::of((string) $data['amount'], $currency, roundingMode: RoundingMode::Down)->getMinorAmount()->toInt();
                        app(RecordLedgerAdjustment::class)($actor, (int) $data['instructor_id'], $minor, $currency, (string) $data['reason'], filled($data['course_id'] ?? null) ? (int) $data['course_id'] : null);
                    } catch (ValidationException $exception) {
                        Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();
                        $action->halt();

                        return;
                    }
                    Notification::make()->success()->title(__('ledger.adjustment_saved'))->send();
                }),
        ];
    }

    /** @return array<array{label: string, value: int|string, icon?: string, tone?: string}> */
    protected function getWorkspaceSummary(): array
    {
        $items = [];
        foreach (app(LedgerTotals::class)->forInstructor() as $currency => $totals) {
            $labelCurrency = RefundResource::currencyLabel($currency);
            $items[] = ['label' => __('ledger.summary_earned', ['currency' => $labelCurrency]), 'value' => RefundResource::money($totals['earnings'], $currency), 'icon' => 'heroicon-o-arrow-trending-up', 'tone' => 'success'];
            $items[] = ['label' => __('ledger.summary_reversed', ['currency' => $labelCurrency]), 'value' => RefundResource::money($totals['reversals'], $currency), 'icon' => 'heroicon-o-arrow-uturn-left'];
            $items[] = ['label' => __('ledger.summary_adjustments', ['currency' => $labelCurrency]), 'value' => RefundResource::money($totals['adjustments'], $currency), 'icon' => 'heroicon-o-adjustments-horizontal'];
            $items[] = ['label' => __('ledger.summary_net', ['currency' => $labelCurrency]), 'value' => RefundResource::money($totals['net'], $currency), 'icon' => 'heroicon-o-scale'];
        }

        return $items;
    }
}
