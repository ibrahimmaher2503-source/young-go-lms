<?php

declare(strict_types=1);

namespace App\Domain\AI\Sandbox;

use App\Domain\AI\Assistant\AssistantContext;
use App\Domain\AI\Assistant\AssistantEngine;
use App\Domain\AI\Assistant\AssistantOutcome;
use App\Domain\AI\Assistant\GuardFailure;
use App\Domain\AI\Assistant\ReplyLanguage;
use App\Domain\AI\Assistant\StudentHash;
use App\Domain\AI\Assistant\StyleTier;
use App\Domain\AI\Guards\InputGuard;
use App\Models\AiPromptVersion;
use App\Models\User;

/**
 * One question through the real guards and the real provider exchange, with no persistence at all: no conversation, message,
 * usage row, quota unit, answer cache entry or safeguarding flag, and no eligibility check. It is the single sandbox used by
 * the instructor test console and by the evaluation harness (AI spec 13.4, 13.5), so test traffic can never reach learner
 * analytics or quotas: there is nothing for them to read.
 */
final class SandboxRunner
{
    public function __construct(private readonly InputGuard $inputs, private readonly AssistantEngine $engine) {}

    /** @param  list<string>  $quizPrompts  prompts of a quiz the asker is pretending to take (evaluation of the quiz guard) */
    public function run(
        AssistantContext $context,
        string $lessonTitle,
        ?User $asker,
        string $question,
        string $locale,
        StyleTier $tier,
        AiPromptVersion $prompt,
        array $quizPrompts = [],
    ): SandboxResult {
        $checked = $this->inputs->inspectAgainst($asker, $question, $context->allPages, $quizPrompts);
        if ($checked->blocked !== null) {
            return new SandboxResult(
                AssistantOutcome::OutOfScope, $checked->message, $checked->page === null ? [] : [$checked->page], $locale, '', false,
                $checked->text, $checked->piiKinds, $checked->blocked, $checked->category, null, null, false, 0, 0, null, $tier,
            );
        }

        $language = ReplyLanguage::for($checked->text, $locale);
        [$request, $rules] = $this->engine->request(
            $prompt, $tier, $language, $lessonTitle, $context->documentVersion, $context,
            [['role' => 'user', 'content' => $checked->text]],
            $asker === null ? StudentHash::of(0) : StudentHash::of((int) $asker->getKey()),
        );
        [$verdict, $how, $attempts, $usage] = $this->engine->exchange($request, $context, $rules);

        $accepted = $verdict !== null && $verdict->passed();
        $outcome = match (true) {
            $verdict === null => AssistantOutcome::Fallback,
            $accepted => $verdict->outcome ?? AssistantOutcome::Fallback,
            $verdict->failure === GuardFailure::Quote => AssistantOutcome::NotInSource,
            default => AssistantOutcome::Fallback,
        };
        $answered = $accepted && $outcome === AssistantOutcome::Answered;
        $answer = $verdict !== null && $answered ? $verdict->answer : '';

        return new SandboxResult(
            $outcome,
            match ($outcome) {
                AssistantOutcome::Answered => $answer,
                AssistantOutcome::NotInSource => __('ai.not_in_source'),
                AssistantOutcome::OutOfScope => __('ai.out_of_scope'),
                AssistantOutcome::Fallback => __('ai.temporarily_unavailable'),
            },
            $answered ? $verdict->pages : [],
            $answered ? $verdict->language : $language->code,
            $answered ? $verdict->quote : '',
            $answered,
            $checked->text,
            $checked->piiKinds,
            null,
            null,
            $verdict === null ? null : $verdict->failure,
            $verdict === null ? $how : null,
            $verdict !== null && $verdict->trimmed,
            $attempts,
            $usage === null ? 0 : $usage->latencyMs,
            $usage?->model,
            $tier,
        );
    }
}
