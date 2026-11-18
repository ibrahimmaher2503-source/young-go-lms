<?php

declare(strict_types=1);

namespace App\Filament\Resources\EssayReview\Pages;

use App\Domain\Assessment\Actions\GradeEssayAnswer;
use App\Domain\Assessment\Actions\ReleaseQuizResult;
use App\Domain\Assessment\QuizAccess;
use App\Domain\Assignments\ReviewAccess;
use App\Domain\Assignments\ReviewRefusedException;
use App\Filament\Resources\EssayReview\EssayReviewResource;
use App\Models\Question;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

/** T-P6-04: grade the written answers of one quiz attempt. Resolved through the scoped resource query, so other courses are a 404. */
final class ReviewEssayAttempt extends Page
{
    use InteractsWithRecord;

    protected static string $resource = EssayReviewResource::class;

    protected string $view = 'filament.review.essay';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(ReviewAccess::allows($this->actor(), QuizAccess::courseOf($this->attempt()->quiz)), 403);
    }

    public function getTitle(): string
    {
        return (string) $this->attempt()->quiz->title;
    }

    /** @return array<string, string> */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('releaseResult')->label(__('review.release_result'))->requiresConfirmation()
                ->visible(fn (): bool => ReleaseQuizResult::canRelease($this->attempt()))
                ->action(function (): void {
                    app(ReleaseQuizResult::class)($this->actor(), $this->attempt());
                    $this->record = $this->resolveRecord($this->attempt()->getKey());
                }),
        ];
    }

    public function gradeAction(): Action
    {
        return Action::make('grade')->label(__('review.actions.grade_answer'))->icon('heroicon-o-star')->modalHeading(__('review.actions.grade_answer'))
            ->schema([
                TextInput::make('points')->label(__('review.points'))->numeric()->minValue(0)->step(0.25)->required(),
                Textarea::make('feedback')->label(__('review.feedback'))->rows(4)->maxLength(5000),
            ])
            ->fillForm(function (array $arguments): array {
                $answer = $this->answerFor((int) ($arguments['question'] ?? 0));

                return ['points' => $answer?->points_awarded === null ? null : (float) $answer->points_awarded, 'feedback' => $answer?->feedback];
            })
            ->action(function (array $data, array $arguments, Action $action): void {
                try {
                    app(GradeEssayAnswer::class)($this->actor(), $this->attempt(), (int) ($arguments['question'] ?? 0), (float) $data['points'], (string) ($data['feedback'] ?? ''));
                } catch (ReviewRefusedException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();
                    $action->halt();

                    return;
                } catch (AuthorizationException) {
                    Notification::make()->danger()->title(__('review.not_allowed'))->send();
                    $action->halt();

                    return;
                }
                $this->record = $this->resolveRecord($this->attempt()->getKey());
                Notification::make()->success()->title(__('review.essay_graded'))->send();
            });
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $attempt = $this->attempt();
        $answers = QuizAnswer::query()->where('quiz_attempt_id', $attempt->getKey())->get()->keyBy('question_id');
        $essays = collect($attempt->snapshot['questions'])->filter(static fn (array $q): bool => $q['type'] === Question::ESSAY)
            ->map(static fn (array $q): array => ['question' => $q, 'answer' => $answers->get((int) $q['id'])])->values();

        return ['attempt' => $attempt, 'learner' => $attempt->learner, 'course' => QuizAccess::courseOf($attempt->quiz), 'essays' => $essays];
    }

    private function answerFor(int $questionId): ?QuizAnswer
    {
        return QuizAnswer::query()->where('quiz_attempt_id', $this->attempt()->getKey())->where('question_id', $questionId)->first();
    }

    private function attempt(): QuizAttempt
    {
        /** @var QuizAttempt $attempt */
        $attempt = $this->record;

        return $attempt;
    }

    private function actor(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
