<?php

declare(strict_types=1);

namespace App\Domain\AI\Guards;

use App\Domain\AI\Contracts\ModerationClassifier;
use App\Models\LessonCurriculumDocument;
use App\Models\User;

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

        if ($this->quiz->matches($learner, $text)) {
            $page = $this->quiz->pageHint($document, $text);

            return new InputVerdict($text, $masked->kinds, GuardKind::QuizGuard, null, $page, $page === null
                ? __('ai.guards.quiz_guard')
                : __('ai.guards.quiz_guard_page', ['page' => $page]));
        }

        return new InputVerdict($text, $masked->kinds);
    }
}
