<?php

declare(strict_types=1);

namespace App\Filament\Resources\SubmissionReview\Pages;

use App\Domain\Assignments\Actions\AddSubmissionFeedback;
use App\Domain\Assignments\Actions\DecideSubmission;
use App\Domain\Assignments\AssignmentAccess;
use App\Domain\Assignments\ReviewAccess;
use App\Domain\Assignments\ReviewRefusedException;
use App\Domain\Assignments\SubmissionReview;
use App\Domain\Assignments\SubmissionRevisions;
use App\Filament\Resources\SubmissionReview\SubmissionReviewResource;
use App\Models\AssignmentSubmission;
use App\Models\SubmissionDecision;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

/**
 * T-P6-04: review one submission. The record is resolved through the resource query, which only holds work the viewer may
 * review, so everything else is a 404. Every action goes through the domain Actions, which check access again.
 */
final class ReviewSubmission extends Page
{
    use InteractsWithRecord;

    protected static string $resource = SubmissionReviewResource::class;

    protected string $view = 'filament.review.submission';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(ReviewAccess::allows($this->actor(), AssignmentAccess::courseOf($this->submission()->assignment)), 403);
    }

    public function getTitle(): string
    {
        return (string) $this->submission()->assignment->title;
    }

    /** @return array<string, string> */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        $decidable = fn (): bool => SubmissionReview::canDecide($this->submission()->status);

        return [
            $this->decisionAction(SubmissionDecision::ACCEPTED, 'accept', 'heroicon-o-check-circle', 'success', false, false)->visible($decidable),
            $this->decisionAction(SubmissionDecision::GRADED, 'grade', 'heroicon-o-star', 'primary', true, false)->visible($decidable),
            $this->decisionAction(SubmissionDecision::REVISION_REQUESTED, 'requestRevision', 'heroicon-o-arrow-uturn-left', 'warning', false, true)
                ->visible(fn (): bool => SubmissionReview::canDecide($this->submission()->status) && SubmissionRevisions::revisionsLeft($this->submission()->assignment, $this->submission()) > 0),
            $this->decisionAction(SubmissionDecision::REJECTED, 'reject', 'heroicon-o-x-circle', 'danger', false, true)->visible($decidable),
            $this->commentAction(),
        ];
    }

    private function decisionAction(string $decision, string $name, string $icon, string $color, bool $scoreRequired, bool $feedbackRequired): Action
    {
        $scoreAllowed = $decision !== SubmissionDecision::REVISION_REQUESTED && $decision !== SubmissionDecision::REJECTED;

        return Action::make($name)->label(__('review.actions.'.$name))->icon($icon)->color($color)->modalHeading(__('review.actions.'.$name))
            ->schema(array_values(array_filter([
                $scoreAllowed ? TextInput::make('score')->label(__('review.score'))->helperText(__('review.score_help'))->numeric()->minValue(0)->maxValue(100)->step(0.01)->required($scoreRequired) : null,
                Textarea::make('feedback')->label(__('review.feedback'))->rows(4)->maxLength(5000)->required($feedbackRequired),
            ])))
            ->action(function (array $data, Action $action) use ($decision): void {
                $score = filled($data['score'] ?? null) ? (float) $data['score'] : null;
                $this->attempt($action, fn () => app(DecideSubmission::class)($this->actor(), $this->submission(), $decision, $score, (string) ($data['feedback'] ?? '')), __('review.decided.'.$decision));
            });
    }

    private function commentAction(): Action
    {
        return Action::make('comment')->label(__('review.actions.comment'))->icon('heroicon-o-chat-bubble-left')->color('gray')->modalHeading(__('review.actions.comment'))
            ->visible(fn (): bool => $this->submission()->status !== AssignmentSubmission::DRAFT)
            ->schema([Textarea::make('body')->label(__('review.feedback'))->rows(4)->maxLength(5000)->required()])
            ->action(function (array $data, Action $action): void {
                $this->attempt($action, fn () => app(AddSubmissionFeedback::class)($this->actor(), $this->submission(), (string) $data['body']), __('review.comment_added'));
            });
    }

    /** Runs the change; a refused input is shown to the reviewer and keeps the dialog open. */
    private function attempt(Action $action, Closure $change, string $done): void
    {
        try {
            $change();
        } catch (ReviewRefusedException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();
            $action->halt();

            return;
        } catch (AuthorizationException) {
            Notification::make()->danger()->title(__('review.not_allowed'))->send();
            $action->halt();

            return;
        }

        $this->record = $this->resolveRecord($this->submission()->getKey());
        Notification::make()->success()->title($done)->send();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $submission = $this->submission();
        $assignment = $submission->assignment;
        $submission->load(['learner', 'revisions.files', 'decisions.reviewer', 'feedback.author']);

        return [
            'submission' => $submission,
            'assignment' => $assignment,
            'course' => AssignmentAccess::courseOf($assignment),
            'revisions' => $submission->revisions->filter(static fn ($r): bool => ! $r->isDraft())->values(),
            'decisions' => $submission->decisions,
            'feedback' => $submission->feedback,
            'revisionsLeft' => SubmissionRevisions::revisionsLeft($assignment, $submission),
        ];
    }

    private function submission(): AssignmentSubmission
    {
        /** @var AssignmentSubmission $submission */
        $submission = $this->record;

        return $submission;
    }

    private function actor(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
