<?php

declare(strict_types=1);

namespace App\Filament\Resources\Settlements\Pages;

use App\Domain\InstructorFinance\Actions\CreateSettlementBatch;
use App\Domain\InstructorFinance\Queries\EligibleLedgerEntries;
use App\Domain\InstructorFinance\Settlement;
use App\Domain\InstructorFinance\SettlementStatus;
use App\Domain\Instructors\Queries\PublicInstructors;
use App\Domain\Shared\Markets\Market;
use App\Filament\Resources\Settlements\SettlementResource;
use App\Filament\Shared\Pages\WorkspaceListRecords;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ListSettlements extends WorkspaceListRecords
{
    protected static string $resource = SettlementResource::class;

    protected function getHeaderActions(): array
    {
        $currency = Market::Egypt->currency();

        return [
            Action::make('create')->label(__('settlements.create_batch'))->icon('heroicon-o-plus')
                ->visible(static fn (): bool => Gate::allows('settlement.manage'))
                ->modalDescription(__('settlements.create_help'))
                ->schema([
                    Select::make('instructor_id')->label(__('ledger.instructor'))->searchable()->native(false)->helperText(__('settlements.create_all_help'))
                        ->options(static fn (): array => User::query()->role(PublicInstructors::ROLES)->orderBy('name')->limit(300)->pluck('name', 'id')->all()),
                    DatePicker::make('until')->label(__('settlements.until'))->helperText(__('settlements.until_help')),
                    TextInput::make('note')->label(__('ledger.note'))->maxLength(255),
                ])
                ->action(static function (array $data) use ($currency): void {
                    $actor = auth()->user();
                    abort_unless($actor instanceof User, 401);
                    $until = filled($data['until'] ?? null) ? Carbon::parse((string) $data['until']) : null;
                    $instructor = filled($data['instructor_id'] ?? null) ? (int) $data['instructor_id'] : null;
                    $ids = app(EligibleLedgerEntries::class)->ids($currency, $instructor, $until);

                    try {
                        $settlement = app(CreateSettlementBatch::class)($actor, $currency, $ids, filled($data['note'] ?? null) ? (string) $data['note'] : null);
                    } catch (ValidationException $exception) {
                        Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();

                        return;
                    }
                    Notification::make()->success()->title(__('settlements.created_ok', ['reference' => $settlement->reference]))->send();
                }),
        ];
    }

    /** @return array<array{label: string, value: int|string, icon?: string, tone?: string}> */
    protected function getWorkspaceSummary(): array
    {
        return [
            ['label' => __('settlements.summary_draft'), 'value' => Settlement::query()->where('status', SettlementStatus::Draft->value)->count(), 'icon' => 'heroicon-o-clock', 'tone' => 'info'],
            ['label' => __('settlements.summary_awaiting'), 'value' => Settlement::query()->where('status', SettlementStatus::Approved->value)->count(), 'icon' => 'heroicon-o-banknotes', 'tone' => 'warning'],
        ];
    }
}
