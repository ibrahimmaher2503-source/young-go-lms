<?php

declare(strict_types=1);

namespace App\Filament\Resources\Statements\Pages;

use App\Domain\InstructorFinance\Actions\GenerateMonthlyStatements;
use App\Domain\Instructors\Queries\PublicInstructors;
use App\Filament\Resources\Statements\StatementResource;
use App\Filament\Shared\Pages\WorkspaceListRecords;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ListStatements extends WorkspaceListRecords
{
    protected static string $resource = StatementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')->label(__('settlements.generate'))->icon('heroicon-o-document-plus')
                ->visible(static fn (): bool => Gate::allows('settlement.manage'))
                ->modalDescription(__('settlements.generate_help'))
                ->schema([
                    Select::make('period')->label(__('settlements.period'))->required()->native(false)->options(static function (): array {
                        $options = [];
                        for ($i = 1; $i <= 12; $i++) {
                            $month = Carbon::now()->startOfMonth()->subMonthsNoOverflow($i);
                            $options[$month->format('Y-m')] = $month->isoFormat('MMMM YYYY');
                        }

                        return $options;
                    }),
                    Select::make('instructor_id')->label(__('ledger.instructor'))->searchable()->native(false)->helperText(__('settlements.generate_all_help'))
                        ->options(static fn (): array => User::query()->role(PublicInstructors::ROLES)->orderBy('name')->limit(300)->pluck('name', 'id')->all()),
                ])
                ->action(static function (array $data): void {
                    $actor = Auth::user();
                    abort_unless($actor instanceof User, 401);
                    try {
                        $created = app(GenerateMonthlyStatements::class)($actor, (string) $data['period'], filled($data['instructor_id'] ?? null) ? (int) $data['instructor_id'] : null);
                    } catch (ValidationException $exception) {
                        Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();

                        throw new Halt;
                    }
                    Notification::make()->success()->title(__('settlements.generated_done', ['count' => $created]))->send();
                }),
        ];
    }
}
