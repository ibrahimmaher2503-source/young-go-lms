<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reviews;

use App\Domain\Engagement\Actions\ApproveCourseReview;
use App\Domain\Engagement\Actions\HideCourseReview;
use App\Domain\Engagement\Actions\RejectCourseReview;
use App\Domain\Engagement\CourseReview;
use App\Domain\Engagement\ReviewStatus;
use App\Domain\Shared\Actions\RecordReviewDecision;
use App\Filament\Resources\Reviews\Pages\ListCourseReviews;
use App\Filament\Shared\Actions\ApproveRejectAction;
use App\Filament\Shared\Tables\LocalizedDateColumn;
use App\Filament\Shared\Tables\StatusBadgeColumn;
use App\Filament\Shared\HasQueueBadge;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

/**
 * T-P9-02: moderation queue for course reviews (YG-GAP-ENG-002). Approve and reject go through the shared
 * ApproveRejectAction; hide (published only) asks for a reason. Every decision is a domain Action that audits itself;
 * the learner gets one notice. Needs review.moderate; nothing here edits a review text.
 */
final class CourseReviewResource extends Resource
{
    use HasQueueBadge;

    protected static ?string $model = CourseReview::class;

    protected static ?string $slug = 'course-reviews';

    public static function getModelLabel(): string
    {
        return __('engagement.review');
    }

    public static function getPluralModelLabel(): string
    {
        return __('engagement.queue_title');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-star';
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('review.moderate');
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
        return parent::getEloquentQuery()->with('course');
    }

    public static function table(Table $table): Table
    {
        $colors = ['pending' => 'info', 'published' => 'success', 'rejected' => 'danger', 'hidden' => 'gray'];
        $is = static fn (CourseReview $r, ReviewStatus ...$states): bool => in_array($r->status, $states, true) && Gate::allows('review.moderate');

        [$approve, $reject] = ApproveRejectAction::make(
            'review.moderate',
            'review_moderation',
            static fn (Model $record, User $actor, ?string $note) => self::guarded(static fn () => app(ApproveCourseReview::class)($actor, self::review($record), $note), 'approved_ok'),
            static fn (Model $record, User $actor, ?string $reason) => self::guarded(static fn () => app(RejectCourseReview::class)($actor, self::review($record), (string) $reason), 'rejected_ok'),
        );

        $hide = Action::make('hide')->label(__('engagement.hide'))->color('gray')->icon('heroicon-o-eye-slash')
            ->requiresConfirmation()->modalHeading(__('engagement.hide_heading'))
            ->schema([Textarea::make('reason')->label(__('common.review_reason'))->required()->minLength(RecordReviewDecision::REASON_MIN)->maxLength(RecordReviewDecision::REASON_MAX)->rows(4)])
            ->visible(static fn (CourseReview $r): bool => $is($r, ReviewStatus::Published))
            ->action(static function (Model $record, array $data): void {
                $actor = Auth::user();
                abort_unless($actor instanceof User, 403);
                self::guarded(static fn () => app(HideCourseReview::class)($actor, self::review($record), is_string($data['reason'] ?? null) ? $data['reason'] : null), 'hidden_ok');
            });

        return $table->defaultSort('created_at')
            ->columns([
                TextColumn::make('course')->label(__('engagement.col_course'))->state(static fn (CourseReview $r): string => (string) $r->course->title),
                TextColumn::make('author_name')->label(__('engagement.col_author')),
                TextColumn::make('rating')->label(__('engagement.col_rating'))->state(static fn (CourseReview $r): string => $r->rating.' / 5'),
                TextColumn::make('body')->label(__('engagement.col_review'))->limit(80)->wrap(),
                LocalizedDateColumn::make('created_at')->label(__('engagement.col_submitted')),
                StatusBadgeColumn::make('status', $colors)->label(__('engagement.col_status'))
                    ->formatStateUsing(static fn (mixed $state): string => __('engagement.state_'.($state instanceof ReviewStatus ? $state->value : (string) $state).'_short')),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('engagement.filter_status'))->default(ReviewStatus::Pending->value)
                    ->options(collect(ReviewStatus::cases())->mapWithKeys(static fn (ReviewStatus $s): array => [$s->value => __('engagement.state_'.$s->value.'_short')])->all()),
            ])
            ->recordActions([
                Action::make('details')->label(__('engagement.details'))->icon('heroicon-o-eye')->visible(static fn (): bool => Gate::allows('review.moderate'))
                    ->modalHeading(__('engagement.review'))->modalSubmitAction(false)
                    ->modalContent(static fn (CourseReview $record): HtmlString => self::details($record)),
                $approve->visible(static fn (CourseReview $r): bool => $is($r, ReviewStatus::Pending, ReviewStatus::Hidden)),
                $reject->visible(static fn (CourseReview $r): bool => $is($r, ReviewStatus::Pending)),
                $hide,
            ]);
    }

    private static function review(Model $record): CourseReview
    {
        abort_unless($record instanceof CourseReview, 404);

        return $record;
    }

    /** Runs a domain call; a refusal shows as a danger notification and halts the action. */
    private static function guarded(Closure $call, string $successKey): void
    {
        try {
            $call();
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();

            throw new Halt;
        }
        Notification::make()->success()->title(__('engagement.'.$successKey))->send();
    }

    private static function details(CourseReview $r): HtmlString
    {
        $rows = [
            __('engagement.col_course') => (string) $r->course->title,
            __('engagement.col_author') => $r->author_name,
            __('engagement.col_rating') => $r->rating.' / 5',
            __('engagement.col_review') => $r->body,
            __('engagement.col_reason') => (string) $r->moderation_reason,
        ];
        $html = '<dl class="space-y-2">';
        foreach ($rows as $label => $value) {
            $html .= '<div><dt class="text-sm text-gray-500">'.e($label).'</dt><dd class="whitespace-pre-line break-words font-semibold" dir="auto">'.e($value).'</dd></div>';
        }

        return new HtmlString($html.'</dl>');
    }

    protected static function queueCount(): int
    {
        return self::getEloquentQuery()->where('status', ReviewStatus::Pending->value)->count();
    }

    public static function getPages(): array
    {
        return ['index' => ListCourseReviews::route('/')];
    }
}
