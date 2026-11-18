<?php

declare(strict_types=1);

namespace App\Domain\AI\Actions;

use App\Domain\AI\Assistant\AnswerCache;
use App\Domain\AI\Assistant\AssistantEngine;
use App\Domain\AI\Assistant\AssistantMode;
use App\Domain\AI\Assistant\AssistantOutcome;
use App\Domain\AI\Assistant\AssistantQuestion;
use App\Domain\AI\Assistant\AssistantRefused;
use App\Domain\AI\Assistant\AssistantReply;
use App\Domain\AI\Assistant\CachedAnswer;
use App\Domain\AI\Assistant\ContextBuilder;
use App\Domain\AI\Assistant\GuardFailure;
use App\Domain\AI\Assistant\GuardVerdict;
use App\Domain\AI\Assistant\ReplyLanguage;
use App\Domain\AI\Assistant\StudentHash;
use App\Domain\AI\Assistant\StyleTier;
use App\Domain\AI\AssistantEligibility;
use App\Domain\AI\Dto\AiCompletionResult;
use App\Domain\AI\Guards\GuardKind;
use App\Domain\AI\Guards\InputGuard;
use App\Domain\AI\Guards\InputVerdict;
use App\Domain\AI\Quota\BudgetGate;
use App\Domain\AI\Quota\QuotaLedger;
use App\Domain\AI\Quota\QuotaReservation;
use App\Domain\AI\Resilience\ProviderRegistry;
use App\Domain\AI\UnavailableReason;
use App\Domain\Audit\AuditLogger;
use App\Domain\Entitlements\EntitlementService;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiPromptVersion;
use App\Models\AiSafeguardingFlag;
use App\Models\AiUsage;
use App\Models\Lesson;
use App\Models\LessonCurriculumDocument;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * The assistant request pipeline (AI spec 7, T-P7-01): eligibility, thread limits, lesson-PDF-only context, a second
 * eligibility check right before the prompt is built, the provider call through the contract, the output guard with one
 * retry, and persistence with audit. T-P7-02 adds, in order: input guards (PII masking, moderation, injection heuristics, active-quiz
 * guard) before anything is stored or sent, the per-lesson answer cache, and the daily quota reserve/commit/refund plus budget gate.
 *
 * Nothing the provider returns is shown unless the output guard accepted it, so the answer is released in one piece and the
 * HTTP layer streams the validated text. The assistant has no tools and writes nothing but its own message rows.
 */
final class AskLessonAssistant
{
    public function __construct(
        private readonly AssistantEligibility $eligibility,
        private readonly EntitlementService $entitlements,
        private readonly AssistantEngine $engine,
        private readonly ContextBuilder $contexts,
        private readonly AuditLogger $audit,
        private readonly InputGuard $inputs,
        private readonly AnswerCache $cache,
        private readonly QuotaLedger $quota,
        private readonly BudgetGate $budget,
    ) {}

    public function __invoke(User $learner, Lesson $lesson, AssistantQuestion $input): AssistantReply
    {
        if ($learner->getAttribute('status') !== 'active' || ! $learner->can('ai.ask')) {
            throw new AuthorizationException;
        }

        $availability = $this->eligibility->check($learner, $lesson);
        if ($availability->reason !== null) {
            throw AssistantRefused::unavailable($availability->reason);
        }

        // The document is resolved from the lesson on the server; the client can name neither a document nor another lesson.
        $document = $lesson->activeCurriculumDocument() ?? throw AssistantRefused::unavailable(UnavailableReason::NoApprovedDocument);

        [$conversation, $messages] = $this->thread($learner, $lesson, $document, $input);
        $original = $this->questionText($input, $messages);

        // Input guards run before the question is stored or sent anywhere; from here on only the masked text is used.
        $checked = $this->inputs->inspect($learner, $document, $original);
        $question = $checked->text;
        $tier = StyleTier::forLearner($learner);
        if ($checked->isBlocked()) {
            return $this->persistGuarded($learner, $lesson, $document, $conversation, $input, $checked, $tier);
        }

        $prompt = AiPromptVersion::current() ?? throw AssistantRefused::unavailable(UnavailableReason::GlobalOff);
        $language = ReplyLanguage::for($question, $input->locale);

        // Only a first, normal-mode question without personal data is cacheable: nothing learner-specific can be in the entry.
        $cacheable = $input->mode === AssistantMode::Normal && $messages->isEmpty() && $checked->piiKinds === [];
        $hit = $cacheable ? $this->cache->find((int) $lesson->getKey(), $document->version, $question, $language->code, $tier, $prompt->version) : null;
        if ($hit !== null) {
            $this->assertSourceAvailable($learner, $lesson, (int) $document->getKey());

            return $this->persistCached($learner, $lesson, $document, $conversation, $input, $question, $checked->piiKinds, $tier, $prompt, $hit);
        }

        $this->budget->assertOpen($lesson);
        $reservation = $this->quota->reserve($learner, $lesson);

        try {
            $reply = $this->answer($learner, $lesson, $document, $conversation, $messages, $input, $question, $checked, $tier, $prompt, $language, $reservation);
        } catch (Throwable $exception) {
            $reservation->refund();

            throw $exception;
        }

        if ($cacheable && $reply->answered()) {
            $this->cache->store((int) $lesson->getKey(), $document->version, $question, $language->code, $tier, $prompt->version, $reply->message, $reply->sourcePages, $reply->language);
        }
        $this->budget->alertIfNeeded($lesson);

        return $reply;
    }

    /**
     * The provider path. The unit reserved by the caller is committed when the output guard accepted a model answer (including a
     * model-declared not_in_source or out_of_scope) and refunded on provider error, timeout or guard rejection (ED-010).
     *
     * @param  Collection<int, AiMessage>  $messages
     */
    private function answer(
        User $learner,
        Lesson $lesson,
        LessonCurriculumDocument $document,
        ?AiConversation $conversation,
        Collection $messages,
        AssistantQuestion $input,
        string $question,
        InputVerdict $checked,
        StyleTier $tier,
        AiPromptVersion $prompt,
        ReplyLanguage $language,
        QuotaReservation $reservation,
    ): AssistantReply {
        $context = $this->contexts->build($document, $question);

        $this->assertSourceAvailable($learner, $lesson, (int) $document->getKey(), quotaReserved: true);

        [$request, $rules] = $this->engine->request($prompt, $tier, $language, (string) $lesson->title, $document->version, $context, $this->turns($messages, $question, $input->mode), StudentHash::of((int) $learner->getKey()));

        [$verdict, $how, $attempts, $usageResult] = $this->engine->exchange($request, $context, $rules);

        try {
            $this->assertSourceAvailable($learner, $lesson, (int) $document->getKey(), quotaReserved: true);
        } catch (AssistantRefused|AuthorizationException $exception) {
            $this->recordProviderUsage($learner, $lesson, $usageResult, 'source_unavailable');

            throw $exception;
        }

        $reply = $this->persist($learner, $lesson, $document, $conversation, $input, $question, $tier, $prompt, $verdict, $how, $attempts, $usageResult, $checked->piiKinds);

        if ($verdict !== null && $verdict->passed()) {
            $reservation->commit();
        } else {
            $reservation->refund();
        }

        return $reply;
    }

    /**
     * @return array{0: AiConversation|null, 1: Collection<int, AiMessage>}
     */
    private function thread(User $learner, Lesson $lesson, LessonCurriculumDocument $document, AssistantQuestion $input): array
    {
        if ($input->conversationId === null) {
            if ($input->mode === AssistantMode::Simpler) {
                throw ValidationException::withMessages(['conversation_id' => __('ai.invalid_conversation')]);
            }

            return [null, new Collection];
        }

        $conversation = AiConversation::query()
            ->whereKey($input->conversationId)->where('student_id', $learner->getKey())->where('lesson_id', $lesson->getKey())->first();
        if ($conversation === null) {
            throw ValidationException::withMessages(['conversation_id' => __('ai.invalid_conversation')]);
        }

        // A thread belongs to one document version: a new version starts a new thread.
        if ($conversation->document_version !== $document->version) {
            if ($input->mode === AssistantMode::Simpler) {
                throw ValidationException::withMessages(['conversation_id' => __('ai.invalid_conversation')]);
            }

            return [null, new Collection];
        }

        return [$conversation, $conversation->messages()->get()];
    }

    /** @param  Collection<int, AiMessage>  $messages */
    private function questionText(AssistantQuestion $input, Collection $messages): string
    {
        $users = $messages->where('role', AiMessage::ROLE_USER);

        if ($input->mode === AssistantMode::Simpler) {
            $last = $messages->last();
            $lastUser = $users->last();
            if ($last === null || $lastUser === null || $last->role !== AiMessage::ROLE_ASSISTANT || $last->status !== AssistantOutcome::Answered->value) {
                throw ValidationException::withMessages(['conversation_id' => __('ai.invalid_conversation')]);
            }
            if (($lastUser->guard_flags['mode'] ?? null) === AssistantMode::Simpler->value) {
                throw new AssistantRefused(AssistantRefused::SIMPLER_LIMIT);
            }

            return $lastUser->content;
        }

        $question = trim($input->question);
        $max = (int) config('ai.question_max_chars', 300);
        if ($question === '' || mb_strlen($question) > $max) {
            throw ValidationException::withMessages(['question' => __('ai.question_invalid', ['max' => $max])]);
        }

        $asked = $users->filter(static fn (AiMessage $message): bool => ($message->guard_flags['mode'] ?? null) !== AssistantMode::Simpler->value)->count();
        if ($asked > (int) config('ai.assistant.follow_up_turns', 3)) {
            throw new AssistantRefused(AssistantRefused::THREAD_LIMIT);
        }

        return $question;
    }

    /** Recheck the source before building the prompt and before releasing the answer. */
    public function assertSourceAvailable(User $learner, Lesson $lesson, int $documentId, bool $quotaReserved = false): void
    {
        if ($learner->refresh()->getAttribute('status') !== 'active' || ! $learner->can('ai.ask')) {
            throw new AuthorizationException;
        }

        $this->entitlements->flush();
        $availability = $this->eligibility->check($learner, $lesson->refresh(), $quotaReserved);
        if ($availability->reason !== null) {
            throw AssistantRefused::unavailable($availability->reason);
        }

        $current = $lesson->activeCurriculumDocument();
        if ($current === null || (int) $current->getKey() !== $documentId) {
            throw AssistantRefused::unavailable(UnavailableReason::NoApprovedDocument);
        }
    }

    /**
     * The last answered Q/A pairs of this thread (answers only, never quotes), then the new question.
     *
     * @param  Collection<int, AiMessage>  $messages
     * @return list<array{role: string, content: string}>
     */
    private function turns(Collection $messages, string $question, AssistantMode $mode): array
    {
        $pairs = [];
        $pending = null;
        foreach ($messages as $message) {
            if ($message->role === AiMessage::ROLE_USER) {
                $pending = $message->content;
            } elseif ($pending !== null && $message->status === AssistantOutcome::Answered->value) {
                $pairs[] = [['role' => 'user', 'content' => $pending], ['role' => 'assistant', 'content' => $message->content]];
                $pending = null;
            }
        }

        $turns = [];
        foreach (array_slice($pairs, -max(1, (int) config('ai.assistant.history_pairs', 2))) as $pair) {
            array_push($turns, ...$pair);
        }

        $turns[] = ['role' => 'user', 'content' => $mode === AssistantMode::Simpler
            ? $question."\n\n(Explain the same point again in simpler words, using only the lesson source.)"
            : $question];

        return $turns;
    }

    /** @param  array<string, int>  $pii */
    private function persist(
        User $learner,
        Lesson $lesson,
        LessonCurriculumDocument $document,
        ?AiConversation $conversation,
        AssistantQuestion $input,
        string $question,
        StyleTier $tier,
        AiPromptVersion $prompt,
        ?GuardVerdict $verdict,
        string $how,
        int $attempts,
        ?AiCompletionResult $usage,
        array $pii = [],
    ): AssistantReply {
        // Quote mismatch after the retry means the source does not clearly support an answer: say so, never show the text.
        $accepted = $verdict !== null && $verdict->passed();
        $outcome = match (true) {
            $verdict === null => AssistantOutcome::Fallback,
            $accepted => $verdict->outcome ?? AssistantOutcome::Fallback,
            $verdict->failure === GuardFailure::Quote => AssistantOutcome::NotInSource,
            default => AssistantOutcome::Fallback,
        };
        $answered = $accepted && $outcome === AssistantOutcome::Answered;
        $reason = $verdict === null ? $how : $verdict->failure?->value;
        $answer = $answered ? $verdict->answer : '';
        $pages = $answered ? $verdict->pages : [];
        $language = $answered ? $verdict->language : $input->locale;
        $trimmed = $verdict !== null && $verdict->trimmed;
        $text = match ($outcome) {
            AssistantOutcome::Answered => $answer,
            AssistantOutcome::NotInSource => __('ai.not_in_source'),
            AssistantOutcome::OutOfScope => __('ai.out_of_scope'),
            AssistantOutcome::Fallback => __('ai.temporarily_unavailable'),
        };
        $item = $lesson->item;
        $course = $item->course;
        $model = $usage?->model;
        $result = $outcome === AssistantOutcome::Fallback ? ($reason ?? 'fallback') : $outcome->value;

        return DB::transaction(function () use ($learner, $lesson, $document, $conversation, $input, $question, $tier, $prompt, $outcome, $answered, $reason, $text, $pages, $language, $trimmed, $attempts, $usage, $model, $result, $course, $pii): AssistantReply {
            $conversation ??= AiConversation::query()->create([
                'student_id' => $learner->getKey(), 'course_id' => $course->getKey(), 'lesson_id' => $lesson->getKey(),
                'document_version' => $document->version, 'locale' => $input->locale, 'style_tier' => $tier->value,
                'video_position' => $input->videoPosition, 'status' => 'open',
            ]);

            AiMessage::query()->create([
                'conversation_id' => $conversation->getKey(), 'role' => AiMessage::ROLE_USER, 'content' => $question,
                'guard_flags' => ['mode' => $input->mode->value] + ($pii === [] ? [] : ['pii' => $pii]), 'cache_hit' => false,
            ]);

            $message = AiMessage::query()->create([
                'conversation_id' => $conversation->getKey(), 'role' => AiMessage::ROLE_ASSISTANT,
                'content' => $answered ? $text : '',
                'status' => $outcome->value,
                'source_pages' => $answered ? $pages : null,
                'quote_verified' => $answered ? true : null,
                'guard_flags' => ['attempts' => $attempts, 'reason' => $reason, 'trimmed' => $trimmed, 'mode' => $input->mode->value],
                'prompt_version' => $prompt->version, 'model' => $model, 'cache_hit' => false,
            ]);

            $this->recordProviderUsage($learner, $lesson, $usage, $result);

            if ($this->audit->record('ai.assistant_asked', $conversation, (int) $learner->getKey(), null, [
                'lesson_id' => $lesson->getKey(), 'document_version' => $document->version, 'status' => $outcome->value,
                'prompt_version' => $prompt->version, 'quote_verified' => $answered, 'attempts' => $attempts, 'mode' => $input->mode->value,
            ]) === null) {
                throw new RuntimeException('Unable to record assistant audit.');
            }

            return new AssistantReply($outcome, $text, $pages, $language, $answered, (int) $message->getKey(), (int) $conversation->getKey(), (int) $document->getKey());
        });
    }

    private function recordProviderUsage(User $learner, Lesson $lesson, ?AiCompletionResult $usage, string $result): void
    {
        AiUsage::query()->create([
            'student_hash' => StudentHash::of((int) $learner->getKey()), 'lesson_id' => $lesson->getKey(),
            'provider' => ProviderRegistry::currentKey(), 'model' => $usage?->model,
            'input_tokens' => $usage?->usage->inputTokens ?? 0,
            'cached_tokens' => $usage?->usage->cachedTokens ?? 0,
            'output_tokens' => $usage?->usage->outputTokens ?? 0,
            'cost_minor' => $usage === null ? 0 : $usage->costMinor,
            'latency_ms' => $usage === null ? 0 : $usage->latencyMs,
            'result' => $result,
        ]);
    }

    /**
     * A question an input guard stopped: no provider call, no quota unit, a fixed localized message. It is stored masked as an
     * out_of_scope turn, counted in ai_usage by guard, and audited (quiz_guard event; safeguarding raises a flag for staff).
     */
    private function persistGuarded(
        User $learner,
        Lesson $lesson,
        LessonCurriculumDocument $document,
        ?AiConversation $conversation,
        AssistantQuestion $input,
        InputVerdict $checked,
        StyleTier $tier,
    ): AssistantReply {
        $kind = $checked->blocked ?? throw new RuntimeException('Guard verdict without a guard.');
        $course = $lesson->item->course;
        // Self-harm from anyone who is not a known adult is flagged for designated admins (O-015, O-028); adults only get the message.
        $flag = $kind === GuardKind::Safeguarding && ! $this->eligibility->isAdult($learner);
        $pages = $checked->page === null ? [] : [$checked->page];

        return DB::transaction(function () use ($learner, $lesson, $document, $conversation, $input, $checked, $tier, $kind, $course, $flag, $pages): AssistantReply {
            $conversation ??= AiConversation::query()->create([
                'student_id' => $learner->getKey(), 'course_id' => $course->getKey(), 'lesson_id' => $lesson->getKey(),
                'document_version' => $document->version, 'locale' => $input->locale, 'style_tier' => $tier->value,
                'video_position' => $input->videoPosition, 'status' => 'open',
            ]);

            AiMessage::query()->create([
                'conversation_id' => $conversation->getKey(), 'role' => AiMessage::ROLE_USER, 'content' => $checked->text,
                'guard_flags' => ['mode' => $input->mode->value] + ($checked->piiKinds === [] ? [] : ['pii' => $checked->piiKinds]), 'cache_hit' => false,
            ]);

            $message = AiMessage::query()->create([
                'conversation_id' => $conversation->getKey(), 'role' => AiMessage::ROLE_ASSISTANT, 'content' => '',
                'status' => AssistantOutcome::OutOfScope->value, 'source_pages' => null, 'quote_verified' => null,
                'guard_flags' => ['guard' => $kind->value, 'category' => $checked->category?->value, 'page' => $checked->page, 'mode' => $input->mode->value],
                'cache_hit' => false,
            ]);

            AiUsage::query()->create([
                'student_hash' => StudentHash::of((int) $learner->getKey()), 'lesson_id' => $lesson->getKey(),
                'provider' => ProviderRegistry::currentKey(), 'model' => null,
                'input_tokens' => 0, 'cached_tokens' => 0, 'output_tokens' => 0, 'cost_minor' => 0, 'latency_ms' => 0, 'result' => $kind->value,
            ]);

            if ($flag) {
                AiSafeguardingFlag::query()->create([
                    'student_id' => $learner->getKey(), 'conversation_id' => $conversation->getKey(),
                    'category' => $checked->category->value ?? 'self_harm', 'status' => 'open',
                ]);
            }

            $event = match (true) {
                $kind === GuardKind::QuizGuard => 'ai.quiz_guard',
                $flag => 'ai.safeguarding_flagged',
                default => 'ai.input_blocked',
            };
            if ($this->audit->record($event, $conversation, (int) $learner->getKey(), null, [
                'lesson_id' => $lesson->getKey(), 'document_version' => $document->version, 'guard' => $kind->value,
                'category' => $checked->category?->value, 'page' => $checked->page,
            ]) === null) {
                throw new RuntimeException('Unable to record assistant audit.');
            }

            return new AssistantReply(AssistantOutcome::OutOfScope, $checked->message, $pages, $input->locale, false, (int) $message->getKey(), (int) $conversation->getKey(), (int) $document->getKey(), $kind->value);
        });
    }

    /**
     * An answer served from the cache: no provider call and no quota unit. Entitlement was re-checked by the caller.
     *
     * @param  array<string, int>  $pii
     */
    private function persistCached(
        User $learner,
        Lesson $lesson,
        LessonCurriculumDocument $document,
        ?AiConversation $conversation,
        AssistantQuestion $input,
        string $question,
        array $pii,
        StyleTier $tier,
        AiPromptVersion $prompt,
        CachedAnswer $hit,
    ): AssistantReply {
        $course = $lesson->item->course;

        return DB::transaction(function () use ($learner, $lesson, $document, $conversation, $input, $question, $pii, $tier, $prompt, $hit, $course): AssistantReply {
            $conversation ??= AiConversation::query()->create([
                'student_id' => $learner->getKey(), 'course_id' => $course->getKey(), 'lesson_id' => $lesson->getKey(),
                'document_version' => $document->version, 'locale' => $input->locale, 'style_tier' => $tier->value,
                'video_position' => $input->videoPosition, 'status' => 'open',
            ]);

            AiMessage::query()->create([
                'conversation_id' => $conversation->getKey(), 'role' => AiMessage::ROLE_USER, 'content' => $question,
                'guard_flags' => ['mode' => $input->mode->value] + ($pii === [] ? [] : ['pii' => $pii]), 'cache_hit' => false,
            ]);

            $message = AiMessage::query()->create([
                'conversation_id' => $conversation->getKey(), 'role' => AiMessage::ROLE_ASSISTANT, 'content' => $hit->answer,
                'status' => AssistantOutcome::Answered->value, 'source_pages' => $hit->pages, 'quote_verified' => true,
                'guard_flags' => ['attempts' => 0, 'reason' => 'cache_hit', 'trimmed' => false, 'mode' => $input->mode->value],
                'prompt_version' => $prompt->version, 'model' => null, 'cache_hit' => true,
            ]);

            AiUsage::query()->create([
                'student_hash' => StudentHash::of((int) $learner->getKey()), 'lesson_id' => $lesson->getKey(),
                'provider' => ProviderRegistry::currentKey(), 'model' => null,
                'input_tokens' => 0, 'cached_tokens' => 0, 'output_tokens' => 0, 'cost_minor' => 0, 'latency_ms' => 0, 'result' => 'cache_hit',
            ]);

            if ($this->audit->record('ai.assistant_asked', $conversation, (int) $learner->getKey(), null, [
                'lesson_id' => $lesson->getKey(), 'document_version' => $document->version, 'status' => AssistantOutcome::Answered->value,
                'prompt_version' => $prompt->version, 'quote_verified' => true, 'attempts' => 0, 'mode' => $input->mode->value, 'cache_hit' => true,
            ]) === null) {
                throw new RuntimeException('Unable to record assistant audit.');
            }

            return new AssistantReply(AssistantOutcome::Answered, $hit->answer, $hit->pages, $hit->language, true, (int) $message->getKey(), (int) $conversation->getKey(), (int) $document->getKey(), null, true);
        });
    }
}
