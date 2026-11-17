<?php

declare(strict_types=1);

namespace App\Filament\Resources\EssayReview;

use App\Domain\Assessment\QuizScoring;
use App\Domain\Assignments\ReviewAccess;
use App\Filament\Resources\EssayReview\Pages\ListEssayReviews;
use App\Filament\Resources\EssayReview\Pages\ReviewEssayAttempt;
use App\Filament\Shared\Tables\LocalizedDateColumn;
use App\Models\Course;
use App\Models\QuizAttempt;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * T-P6-04: submitted quiz attempts that hold written (essay) answers, for the people who may review the course. The
 * default view is attempts still waiting for a grade; "reviewed" shows attempts that were already graded.
 */
final class EssayReviewResource extends Resource
{
    protected static ?string $model = QuizAttempt::class;

    protected static ?string $slug = 'essay-review';

    public static function getModelLabel(): string
    {
        return __('review.attempt');
    }

    public static function getPluralModelLabel(): string
    {
        return __('review.essay_queue_title');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-pencil-square';
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
            ->where('status', QuizAttempt::SUBMITTED)
            ->whereHas('quiz.item', static fn (Builder $item): Builder => $item->whereIn('course_id', $ids))
            ->where(static fn (Builder $q): Builder => $q->where('needs_review', true)->orWhereHas('answers', static fn (Builder $a): Builder => $a->whereNotNull('graded_by')))
            ->with(['learner', 'quiz.item.course']);
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
            ->recordUrl(static fn (QuizAttempt $record): string => self::getUrl('review', ['record' => $record]))
            ->columns([
                TextColumn::make('learner.name')->label(__('review.learner'))->searchable(),
                TextColumn::make('quiz')->label(__('review.quiz'))->state(static fn (QuizAttempt $r): string => (string) $r->quiz->title),
                TextColumn::make('course')->label(__('review.course'))->state(static fn (QuizAttempt $r): string => (string) $r->quiz->item?->course?->title),
                TextColumn::make('attempt_no')->label(__('review.attempt_no')),
                TextColumn::make('pending')->label(__('review.pending_answers'))->state(static fn (QuizAttempt $r): int => QuizScoring::pendingEssays($r)),
                LocalizedDateColumn::make('submitted_at')->label(__('review.submitted_at')),
            ])
            ->filters([
                SelectFilter::make('course')->label(__('review.course'))->options(static fn (): array => self::courseOptions())
                    ->query(static fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('quiz.item', static fn (Builder $item): Builder => $item->where('course_id', (int) $data['value']))
                        : $query),
                SelectFilter::make('state')->label(__('review.status'))->default('pending')->selectablePlaceholder(false)
                    ->options(['pending' => __('review.state.pending'), 'reviewed' => __('review.state.reviewed')])
                    ->query(static fn (Builder $query, array $data): Builder => match ($data['value'] ?? 'pending') {
                        'reviewed' => $query->where('needs_review', false),
                        default => $query->where('needs_review', true),
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEssayReviews::route('/'),
            'review' => ReviewEssayAttempt::route('/{record}'),
        ];
    }
}
