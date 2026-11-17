<?php

declare(strict_types=1);

namespace App\Domain\AI\Guards;

use App\Domain\AI\Contracts\ModerationClassifier;
use App\Models\LessonCurriculumDocument;
use App\Models\User;
use Closure;

/**
 * Everything that happens to a question before it is stored or sent anywhere (AI spec 8.1, 8.3), in this order:
 * PII masking, moderation (self-harm routes to safeguarding), injection heuristics, active-quiz guard. Moderation and the
 * injection heuristics only ever see masked text. A blocked question never reaches the provider and consumes no quota.
 */
final class InputGuard
{
    public function __construct(
        private readonly PiiMasker $masker,
        private readonly ModerationClassifier $moderation,
        private readonly InjectionHeuristics $injection,
        private readonly QuizQuestionMatcher $quiz,
    ) {}

    public function inspect(User $learner, LessonCurriculumDocument $document, string $question): InputVerdict
    {
        return $this->run(
            $learner,
            $question,
            fn (string $text): bool => $this->quiz->matches($learner, $text),
            fn (string $text): ?int => $this->quiz->pageHint($document, $text),
        );
    }

    /**
     * The same guards for a question that is not tied to a learner's open quiz attempt (instructor test console, evaluation):
     * the quiz guard compares against the given prompts and the page hint reads the given pages. Nothing is read or written.
     *
     * @param  array<int, string>  $pages  page number => approved text
     * @param  list<string>  $quizPrompts
     */
    public function inspectAgainst(?User $asker, string $question, array $pages, array $quizPrompts): InputVerdict
    {
        return $this->run(
            $asker,
            $question,
            fn (string $text): bool => $this->quiz->matchesPrompts($text, $quizPrompts),
            fn (string $text): ?int => $this->quiz->pageHintFor($pages, $text),
        );
    }

    /**
     * @param  Closure(string): bool  $quizMatches
     * @param  Closure(string): ?int  $pageHint
     */
    private function run(?User $learner, string $question, Closure $quizMatches, Closure $pageHint): InputVerdict
    {
        $masked = $this->masker->mask($question, $learner);
        $text = $masked->text;

        $category = $this->moderation->classify($text);
        if ($category === ModerationCategory::SelfHarm) {
            return new InputVerdict($text, $masked->kinds, GuardKind::Safeguarding, $category, null, __('ai.guards.safeguarding'));
        }
        if ($category !== null) {
            return new InputVerdict($text, $masked->kinds, GuardKind::Moderation, $category, null, __('ai.guards.moderation'));
        }

        if ($this->injection->detects($text)) {
            return new InputVerdict($text, $masked->kinds, GuardKind::Injection, null, null, __('ai.guards.injection'));
        }

        if ($quizMatches($text)) {
            $page = $pageHint($text);

            return new InputVerdict($text, $masked->kinds, GuardKind::QuizGuard, null, $page, $page === null
                ? __('ai.guards.quiz_guard')
                : __('ai.guards.quiz_guard_page', ['page' => $page]));
        }

        return new InputVerdict($text, $masked->kinds);
    }
}
