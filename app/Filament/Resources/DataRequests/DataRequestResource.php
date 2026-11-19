<?php

declare(strict_types=1);

namespace App\Filament\Resources\DataRequests;

use App\Domain\Privacy\Actions\ApproveDataRequest;
use App\Domain\Privacy\Actions\CompleteDataRequest;
use App\Domain\Privacy\Actions\RejectDataRequest;
use App\Domain\Privacy\DataRequest;
use App\Domain\Privacy\DataRequestStatus;
use App\Domain\Privacy\DataRequestType;
use App\Filament\Resources\DataRequests\Pages\ListDataRequests;
use App\Filament\Shared\Actions\ApproveRejectAction;
use App\Filament\Shared\HasQueueBadge;
use App\Filament\Shared\Tables\LocalizedDateColumn;
use App\Filament\Shared\Tables\StatusBadgeColumn;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * T-P9-08: privacy request queue (export, correction, deletion/anonymization). Approve and reject use the shared review action;
 * Complete runs the export or the anonymization. Everything needs privacy.manage; rows are never edited or deleted here.
 */
final class DataRequestResource extends Resource
{
    use HasQueueBadge;

    protected static ?string $model = DataRequest::class;

    protected static ?string $slug = 'privacy-requests';

    public static function getModelLabel(): string
    {
        return __('privacy.request');
    }

    public static function getPluralModelLabel(): string
    {
        return __('privacy.queue_title');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-shield-check';
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->getAttribute('status') === 'active' && Gate::allows('privacy.manage');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /** @return Builder<Model> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['subject', 'requester']);
    }

    public static function table(Table $table): Table
    {
        $colors = ['requested' => 'info', 'approved' => 'warning', 'completed' => 'success', 'rejected' => 'gray', 'cancelled' => 'gray'];
        $canManage = static fn (): bool => Gate::allows('privacy.manage');

        [$approve, $reject] = ApproveRejectAction::make(
            'privacy.manage',
            'privacy_review',
            static fn (Model $record, User $actor, ?string $note) => self::guarded(static fn () => app(ApproveDataRequest::class)($actor, self::request($record), $note), 'approved_ok'),
            static fn (Model $record, User $actor, ?string $reason) => self::guarded(static fn () => app(RejectDataRequest::class)($actor, self::request($record), (string) $reason), 'rejected_ok'),
        );

        $complete = Action::make('complete')->label(__('privacy.complete'))->icon('heroicon-o-check-badge')->color('warning')
            ->visible(static fn (DataRequest $r): bool => $r->status === DataRequestStatus::Approved && $canManage())
            ->modalHeading(__('privacy.complete_heading'))
            ->modalDescription(static fn (DataRequest $r): string => __('privacy.complete_help_'.$r->type->value))
            ->schema(static fn (DataRequest $r): array => [
                Textarea::make('note')->label(__('privacy.completion_note'))->rows(3)->maxLength(1000)
                    ->required($r->type === DataRequestType::Correction)->minLength($r->type === DataRequestType::Correction ? 10 : 0),
            ])
            ->requiresConfirmation()
            ->action(static function (DataRequest $record, array $data): void {
                $actor = Auth::user();
                abort_unless($actor instanceof User, 403);
                $note = $data['note'] ?? null;
                self::guarded(static fn () => app(CompleteDataRequest::class)($actor, $record, is_string($note) ? $note : null), 'completed_ok');
            });

        return $table->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')->label(__('privacy.col_id'))->fontFamily('mono'),
                TextColumn::make('type')->label(__('privacy.col_type'))->badge()->state(static fn (DataRequest $r): string => $r->type->label()),
                TextColumn::make('subject')->label(__('privacy.col_subject'))->state(static fn (DataRequest $r): string => (string) $r->subject?->getAttribute('name')),
                TextColumn::make('requester')->label(__('privacy.col_requester'))->state(static fn (DataRequest $r): string => (string) $r->requester?->getAttribute('name')),
                TextColumn::make('details')->label(__('privacy.col_details'))->limit(60)->tooltip(static fn (DataRequest $r): ?string => $r->details),
                LocalizedDateColumn::make('created_at')->label(__('privacy.col_requested')),
                StatusBadgeColumn::make('status', $colors)->label(__('privacy.col_status'))
                    ->formatStateUsing(static fn (mixed $state): string => __('privacy.status_'.($state instanceof DataRequestStatus ? $state->value : (string) $state))),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('privacy.filter_status'))
                    ->options(collect(DataRequestStatus::cases())->mapWithKeys(static fn (DataRequestStatus $s): array => [$s->value => $s->label()])->all()),
                SelectFilter::make('type')->label(__('privacy.filter_type'))
                    ->options(collect(DataRequestType::cases())->mapWithKeys(static fn (DataRequestType $t): array => [$t->value => $t->label()])->all()),
            ])
            ->recordActions([
                $approve->visible(static fn (DataRequest $r): bool => $r->status === DataRequestStatus::Requested && $canManage()),
                $reject->visible(static fn (DataRequest $r): bool => $r->status->isOpen() && $canManage()),
                $complete,
            ]);
    }

    private static function request(Model $record): DataRequest
    {
        abort_unless($record instanceof DataRequest, 404);

        return $record;
    }

    /** Runs a domain call; a refusal shows as a danger notification and halts the action (the transaction rolls back). */
    public static function guarded(Closure $call, string $successKey): void
    {
        try {
            $call();
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();

            throw new Halt;
        } catch (AuthorizationException) {
            Notification::make()->danger()->title(__('privacy.denied'))->send();

            throw new Halt;
        }
        Notification::make()->success()->title(__('privacy.'.$successKey))->send();
    }

    protected static function queueCount(): int
    {
        return self::getEloquentQuery()->where('status', DataRequestStatus::Requested->value)->count();
    }

    public static function getPages(): array
    {
        return ['index' => ListDataRequests::route('/')];
    }
}
