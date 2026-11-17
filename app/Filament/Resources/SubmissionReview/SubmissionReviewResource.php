<?php

declare(strict_types=1);

namespace App\Filament\Resources\SubmissionReview;

use App\Domain\Assignments\ReviewAccess;
use App\Domain\Assignments\SubmissionReview;
use App\Filament\Resources\SubmissionReview\Pages\ListSubmissionReviews;
use App\Filament\Resources\SubmissionReview\Pages\ReviewSubmission;
use App\Filament\Shared\Tables\LocalizedDateColumn;
use App\Filament\Shared\Tables\StatusBadgeColumn;
use App\Models\AssignmentSubmission;
use App\Models\Course;
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
 * T-P6-04: the instructor queue of handed-in assignment work. It only ever lists submissions of courses the viewer may
 * review (assessment permission plus the course update gate), so anything else resolves to a 404 on the review page.
 * Overdue means: still waiting for a review while the assignment due date has passed.
 */
final class SubmissionReviewResource extends Resource
{
    protected static ?string $model = AssignmentSubmission::class;

    protected static ?string $slug = 'submission-review';

    public static function getModelLabel(): string
    {
        return __('review.submission');
    }

    public static function getPluralModelLabel(): string
    {
        return __('review.queue_title');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-clipboard-document-check';
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->getAttribute('status') === 'active' && $user->can('assessment.manage');
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

    /** @return list<int> */
    public static function courseIds(): array
    {
        $user = Auth::user();

        return $user instanceof User ? ReviewAccess::courseIds($user) : [];
    }

    /** @return Builder<Model> */
    public static function getEloquentQuery(): Builder
    {
        $ids = self::courseIds();

        return parent::getEloquentQuery()
            ->where('status', '!=', AssignmentSubmission::DRAFT)
            ->whereHas('assignment.item', static fn (Builder $item): Builder => $item->whereIn('course_id', $ids))
            ->with(['learner', 'assignment.item.course'])
            ->withMax('revisions as last_submitted_at', 'submitted_at');
    }

    /** @return array<int, string> course id => title, only courses the viewer may review */
    public static function courseOptions(): array
    {
        return Course::query()->whereIn('id', self::courseIds())->orderByDesc('id')->get()
            ->mapWithKeys(static fn (Course $c): array => [(int) $c->getKey() => (string) $c->title])->all();
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')
            ->recordUrl(static fn (AssignmentSubmission $record): string => self::getUrl('review', ['record' => $record]))
            ->columns([
                TextColumn::make('learner.name')->label(__('review.learner'))->searchable(),
                TextColumn::make('assignment')->label(__('review.assignment'))->state(static fn (AssignmentSubmission $r): string => (string) $r->assignment->title),
                TextColumn::make('course')->label(__('review.course'))->state(static fn (AssignmentSubmission $r): string => (string) $r->assignment->item?->course?->title),
                StatusBadgeColumn::make('status', ['submitted' => 'warning', 'resubmitted' => 'warning', 'returned' => 'info', 'graded' => 'success', 'accepted' => 'success', 'rejected' => 'danger'])
                    ->label(__('review.status'))->formatStateUsing(static fn (string $state): string => __('assignment.statuses.'.$state)),
                LocalizedDateColumn::make('last_submitted_at')->label(__('review.submitted_at')),
                LocalizedDateColumn::make('assignment.due_at')->label(__('review.due'))->placeholder(__('assignment.no_due_date')),
            ])
            ->filters([
                SelectFilter::make('course')->label(__('review.course'))->options(static fn (): array => self::courseOptions())
                    ->query(static fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('assignment.item', static fn (Builder $item): Builder => $item->where('course_id', (int) $data['value']))
                        : $query),
                SelectFilter::make('status')->label(__('review.status'))
                    ->options(collect(['submitted', 'resubmitted', 'returned', 'graded', 'accepted', 'rejected'])->mapWithKeys(static fn (string $s): array => [$s => __('assignment.statuses.'.$s)])->all()),
                Filter::make('overdue')->label(__('review.overdue'))->toggle()
                    ->query(static fn (Builder $query): Builder => $query->whereIn('status', SubmissionReview::AWAITING)
                        ->whereHas('assignment', static fn (Builder $a): Builder => $a->whereNotNull('due_at')->where('due_at', '<', now()))),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubmissionReviews::route('/'),
            'review' => ReviewSubmission::route('/{record}'),
        ];
    }
}
