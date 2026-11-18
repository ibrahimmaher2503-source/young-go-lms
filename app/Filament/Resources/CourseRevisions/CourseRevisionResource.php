<?php

declare(strict_types=1);

namespace App\Filament\Resources\CourseRevisions;

use App\Domain\Catalog\Revisions\Actions\ApproveCourseRevision;
use App\Domain\Catalog\Revisions\Actions\RejectCourseRevision;
use App\Domain\Catalog\Revisions\Actions\ReviewCourseRevision;
use App\Domain\Catalog\Revisions\CourseRevisionStatus;
use App\Domain\Catalog\Revisions\Queries\CourseRevisions;
use App\Domain\Catalog\Revisions\RevisionDiff;
use App\Filament\Resources\CourseRevisions\Pages\ListCourseRevisions;
use App\Filament\Shared\Actions\ApproveRejectAction;
use App\Filament\Shared\Tables\LocalizedDateColumn;
use App\Filament\Shared\Tables\StatusBadgeColumn;
use App\Models\CourseRevision;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;

/**
 * T-P3-07: review queue of revisions to published courses. Approve and reject go through the shared ApproveRejectAction;
 * RecordReviewDecision rechecks the course's `publish` grant (the author never reviews their own revision), the domain
 * Action applies the revision in one transaction and the decision is audited. Needs `course.publish`.
 */
final class CourseRevisionResource extends Resource
{
    protected static ?string $model = CourseRevision::class;

    protected static ?string $slug = 'course-revisions';

    public static function getModelLabel(): string
    {
        return __('course_revisions.revision');
    }

    public static function getPluralModelLabel(): string
    {
        return __('course_revisions.queue_title');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-document-duplicate';
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can('course.publish') && $user->can('panel.admin.access');
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

    /** @return Builder<CourseRevision> */
    public static function getEloquentQuery(): Builder
    {
        return CourseRevisions::queue(Auth::user());
    }

    public static function table(Table $table): Table
    {
        $colors = ['draft' => 'gray', 'pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'discarded' => 'gray'];
        $pending = static fn (CourseRevision $record): bool => $record->status === CourseRevisionStatus::Pending;
        $canReview = static fn (CourseRevision $record): bool => $pending($record) && Gate::allows(ReviewCourseRevision::ABILITY, $record);

        [$approve, $reject] = ApproveRejectAction::make(
            ReviewCourseRevision::ABILITY,
            ReviewCourseRevision::EVENT_PREFIX,
            static function (Model $record, User $actor, ?string $note): void {
                app(ApproveCourseRevision::class)(self::revision($record), $actor, $note);
            },
            static function (Model $record, User $actor, ?string $reason): void {
                app(RejectCourseRevision::class)(self::revision($record), $actor, $reason);
            },
        );

        return $table->defaultSort('submitted_at')
            ->columns([
                TextColumn::make('course')->label(__('courses.name'))->state(static fn (CourseRevision $r): string => $r->course->title),
                TextColumn::make('number')->label(__('course_revisions.col_number'))->formatStateUsing(static fn (int $state): string => '#'.$state),
                TextColumn::make('author')->label(__('course_revisions.col_author'))->state(static fn (CourseRevision $r): string => $r->author->name ?? '-'),
                TextColumn::make('changes')->label(__('course_revisions.col_changes'))->state(static fn (CourseRevision $r): int => count(RevisionDiff::between($r->base, $r->payload))),
                LocalizedDateColumn::make('submitted_at')->label(__('course_revisions.col_submitted')),
                StatusBadgeColumn::make('status', $colors)->label(__('course_revisions.col_status'))
                    ->formatStateUsing(static fn (mixed $state): string => __('course_revisions.state.'.($state instanceof CourseRevisionStatus ? $state->value : (string) $state))),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('course_revisions.col_status'))->default(CourseRevisionStatus::Pending->value)
                    ->options(collect(CourseRevisionStatus::cases())->mapWithKeys(static fn (CourseRevisionStatus $s): array => [$s->value => __('course_revisions.state.'.$s->value)])->all()),
            ])
            ->recordActions([
                Action::make('compare')->label(__('course_revisions.compare'))->icon('heroicon-o-eye')
                    ->modalHeading(__('course_revisions.compare'))->modalWidth('5xl')->modalSubmitAction(false)
                    ->modalContent(static fn (CourseRevision $record): HtmlString => new HtmlString(view('filament.courses.revision-compare', ['changes' => RevisionDiff::between($record->base, $record->payload)])->render())),
                $approve->visible($canReview),
                $reject->visible($canReview),
            ]);
    }

    private static function revision(Model $record): CourseRevision
    {
        abort_unless($record instanceof CourseRevision, 404);

        return $record;
    }

    public static function getPages(): array
    {
        return ['index' => ListCourseRevisions::route('/')];
    }
}
