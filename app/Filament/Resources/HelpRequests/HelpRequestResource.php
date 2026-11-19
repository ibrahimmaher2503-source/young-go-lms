<?php

declare(strict_types=1);

namespace App\Filament\Resources\HelpRequests;

use App\Domain\AI\Help\HelpRequestAccess;
use App\Domain\AI\Help\HelpRequestStatus;
use App\Filament\Resources\HelpRequests\Pages\ListHelpRequests;
use App\Filament\Resources\HelpRequests\Pages\ViewHelpRequest;
use App\Filament\Shared\Tables\LocalizedDateColumn;
use App\Filament\Shared\Tables\StatusBadgeColumn;
use App\Models\Course;
use App\Models\HelpRequest;
use App\Filament\Shared\HasQueueBadge;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * T-P7-04: the queue of help requests raised from the lesson assistant. An instructor sees only the requests assigned to
 * them; staff with help_request.manage (admins, support) see all. The query is scoped by HelpRequestAccess, so anyone else
 * resolves to a 404 on the request page. Overdue means: still waiting for the instructor and past the SLA time.
 */
final class HelpRequestResource extends Resource
{
    use HasQueueBadge;

    protected static ?string $model = HelpRequest::class;

    protected static ?string $slug = 'help-requests';

    public static function getModelLabel(): string
    {
        return __('help.queue.request');
    }

    public static function getPluralModelLabel(): string
    {
        return __('help.queue.title');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-lifebuoy';
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        return $user instanceof User && HelpRequestAccess::isStaff($user);
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
        $user = Auth::user();
        $query = parent::getEloquentQuery()->with(['student', 'course', 'lesson', 'instructor']);

        return $user instanceof User ? HelpRequestAccess::scope($query, $user) : $query->whereRaw('1 = 0');
    }

    /** @return array<int, string> course id => title, only courses that have a request the viewer may see */
    public static function courseOptions(): array
    {
        return Course::query()->whereIn('id', self::getEloquentQuery()->select('course_id'))->orderByDesc('id')->get()
            ->mapWithKeys(static fn (Course $c): array => [(int) $c->getKey() => (string) $c->title])->all();
    }

    public static function table(Table $table): Table
    {
        $colors = [];
        foreach (HelpRequestStatus::cases() as $status) {
            $colors[$status->value] = $status->tone();
        }

        return $table->defaultSort('id', 'desc')
            ->recordUrl(static fn (HelpRequest $record): string => self::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('student.name')->label(__('help.queue.student'))->searchable(),
                TextColumn::make('course')->label(__('help.queue.course'))->state(static fn (HelpRequest $r): string => (string) $r->course->title),
                TextColumn::make('lesson')->label(__('help.queue.lesson'))->state(static fn (HelpRequest $r): string => (string) $r->lesson->title),
                StatusBadgeColumn::make('status', $colors)->label(__('help.queue.status'))
                    ->formatStateUsing(static fn (mixed $state): string => $state instanceof HelpRequestStatus ? $state->label() : (string) $state),
                TextColumn::make('overdue')->label(__('help.queue.sla'))->badge()->color('danger')
                    ->state(static fn (HelpRequest $r): ?string => $r->isOverdue() ? __('help.queue.overdue_badge') : null),
                TextColumn::make('instructor.name')->label(__('help.queue.instructor'))->placeholder(__('help.queue.admin_queue')),
                LocalizedDateColumn::make('created_at')->label(__('help.queue.created')),
                LocalizedDateColumn::make('due_at')->label(__('help.queue.due'))->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('course')->label(__('help.queue.course'))->options(static fn (): array => self::courseOptions())
                    ->query(static fn (Builder $query, array $data): Builder => filled($data['value'] ?? null) ? $query->where('course_id', (int) $data['value']) : $query),
                SelectFilter::make('status')->label(__('help.queue.status'))
                    ->options(collect(HelpRequestStatus::cases())->mapWithKeys(static fn (HelpRequestStatus $s): array => [$s->value => $s->label()])->all()),
                Filter::make('overdue')->label(__('help.queue.overdue'))->toggle()
                    ->query(static fn (Builder $query): Builder => $query->whereIn('status', HelpRequestStatus::awaitingValues())->whereNotNull('due_at')->where('due_at', '<', now())),
            ]);
    }

    protected static function queueCount(): int
    {
        return self::getEloquentQuery()->whereIn('status', HelpRequestStatus::awaitingValues())->count();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHelpRequests::route('/'),
            'view' => ViewHelpRequest::route('/{record}'),
        ];
    }
}
