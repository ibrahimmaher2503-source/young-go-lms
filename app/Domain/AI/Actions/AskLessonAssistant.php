<?php

declare(strict_types=1);

namespace App\Domain\AI\Actions;

use App\Domain\AI\AiProviderNotConfigured;
use App\Domain\AI\Assistant\AssistantContext;
use App\Domain\AI\Assistant\AssistantMode;
use App\Domain\AI\Assistant\AssistantOutcome;
use App\Domain\AI\Assistant\AssistantQuestion;
use App\Domain\AI\Assistant\AssistantRefused;
use App\Domain\AI\Assistant\AssistantReply;
use App\Domain\AI\Assistant\ContextBuilder;
use App\Domain\AI\Assistant\GuardFailure;
use App\Domain\AI\Assistant\GuardVerdict;
use App\Domain\AI\Assistant\OutputGuard;
use App\Domain\AI\Assistant\ReplyLanguage;
use App\Domain\AI\Assistant\StudentHash;
use App\Domain\AI\Assistant\StyleTier;
use App\Domain\AI\AssistantEligibility;
use App\Domain\AI\Contracts\AiProviderInterface;
use App\Domain\AI\Dto\AiCompletionRequest;
use App\Domain\AI\Dto\AiCompletionResult;
use App\Domain\AI\Dto\AiUsage as UsageTokens;
use App\Domain\AI\PromptRenderer;
use App\Domain\AI\UnavailableReason;
use App\Domain\Audit\AuditLogger;
use App\Domain\Entitlements\EntitlementService;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiPromptVersion;
use App\Models\AiUsage;
use App\Models\Lesson;
use App\Models\LessonCurriculumDocument;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * The assistant request pipeline (AI spec 7, T-P7-01): eligibility, thread limits, lesson-PDF-only context, a second
 * eligibility check right before the prompt is built, the provider call through the contract, the output guard with one
 * retry, and persistence with audit. Input guards, cache and quotas belong to T-P7-02.
 *
 * Nothing the provider returns is shown unless the output guard accepted it, so the answer is released in one piece and the
 * HTTP layer streams the validated text. The assistant has no tools and writes nothing but its own message rows.
 */
final class AskLessonAssistant
{
    public function __construct(
        private readonly AssistantEligibility $eligibility,
        private readonly EntitlementService $entitlements,
        private readonly AiProviderInterface $provider,
        private readonly ContextBuilder $contexts,
        private readonly PromptRenderer $prompts,
        private readonly OutputGuard $guard,
        private readonly AuditLogger $audit,
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
        $question = $this->questionText($input, $messages);
        $context = $this->contexts->build($document, $question);

        $this->recheck($learner, $lesson, $document);

        $prompt = AiPromptVersion::current() ?? throw AssistantRefused::unavailable(UnavailableReason::GlobalOff);
        $tier = StyleTier::forLearner($learner);
        $language = ReplyLanguage::for($question, $input->locale);
        $system = $this->prompts->render($prompt, $tier->value, $language->label, (string) $lesson->title, $document->version, $context->promptPages, $language->maxWords());
        $rules = Str::before($system, '<'.config('ai.source_tag', 'lesson_source'));
        $request = new AiCompletionRequest(
            promptVersion: $prompt->version,
            cacheablePrefix: $system,
            turns: $this->turns($messages, $question, $input->mode),
            maxTokens: (int) config('ai.max_output_tokens', 220),
            responseSchema: $this->schema(),
            timeoutSeconds: (int) config('ai.assistant.timeout_seconds', 15),
            userHash: StudentHash::of((int) $learner->getKey()),
            idempotencyKey: (string) Str::uuid(),
        );

        [$verdict, $how, $attempts, $usageResult] = $this->ask($request, $context, $rules);

        return $this->persist($learner, $lesson, $document, $conversation, $input, $question, $tier, $prompt, $verdict, $how, $attempts, $usageResult);
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

    /** Entitlement and document status are checked again right before the prompt is built (AI spec 7.8). */
    private function recheck(User $learner, Lesson $lesson, LessonCurriculumDocument $document): void
    {
        $this->entitlements->flush();
        $availability = $this->eligibility->check($learner, $lesson);
        if ($availability->reason !== null) {
            throw AssistantRefused::unavailable($availability->reason);
        }

        $current = $lesson->activeCurriculumDocument();
        if ($current === null || $current->getKey() !== $document->getKey()) {
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

    /**
     * Calls the provider and applies the output guard, with one retry when the guard rejects. A provider error or timeout
     * is not retried: it ends in the safe fallback so the learner is never kept waiting.
     *
     * @return array{0: GuardVerdict|null, 1: string, 2: int, 3: AiCompletionResult|null}
     */
    private function ask(AiCompletionRequest $request, AssistantContext $context, string $rules): array
    {
        $attempts = 0;
        $verdict = null;
        $result = null;
        $tokens = [0, 0, 0];
        $latency = 0;
        $maxAttempts = 1 + max(0, (int) config('ai.assistant.retries', 1));

        while ($attempts < $maxAttempts) {
            $attempts++;
            try {
                $result = $this->provider->complete($attempts === 1 ? $request : new AiCompletionRequest(
                    $request->promptVersion, $request->cacheablePrefix, $request->turns, $request->maxTokens, $request->responseSchema,
                    $request->timeoutSeconds, $request->userHash, $request->idempotencyKey.':retry',
                ));
            } catch (Throwable $exception) {
                if (! $exception instanceof AiProviderNotConfigured) {
                    report($exception);
                }

                return [null, 'provider_error', $attempts, $this->usage($result, $tokens, $latency)];
            }

            $tokens = [$tokens[0] + $result->usage->inputTokens, $tokens[1] + $result->usage->cachedTokens, $tokens[2] + $result->usage->outputTokens];
            $latency += $result->latencyMs;
            if ($result->latencyMs > $request->timeoutSeconds * 1000) {
                return [null, 'timeout', $attempts, $this->usage($result, $tokens, $latency)];
            }

            $verdict = $this->guard->inspect($result->content, $context, $rules);
            if ($verdict->passed()) {
                break;
            }
        }

        return [$verdict, 'guard', $attempts, $this->usage($result, $tokens, $latency)];
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $tokens
     */
    private function usage(?AiCompletionResult $last, array $tokens, int $latency): ?AiCompletionResult
    {
        return $last === null ? null : new AiCompletionResult(
            $last->content, new UsageTokens($tokens[0], $tokens[1], $tokens[2]), $last->costMinor, $latency,
            $last->providerRequestId, $last->finishReason, $last->model,
        );
    }

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
        $tokens = $usage === null ? [0, 0, 0] : [$usage->usage->inputTokens, $usage->usage->cachedTokens, $usage->usage->outputTokens];
        $model = $usage === null ? null : $usage->model;
        $cost = $usage === null ? 0 : $usage->costMinor;
        $latency = $usage === null ? 0 : $usage->latencyMs;
        $result = $outcome === AssistantOutcome::Fallback ? ($reason ?? 'fallback') : $outcome->value;

        return DB::transaction(function () use ($learner, $lesson, $document, $conversation, $input, $question, $tier, $prompt, $outcome, $answered, $reason, $text, $pages, $language, $trimmed, $attempts, $tokens, $model, $cost, $latency, $result, $course): AssistantReply {
            $conversation ??= AiConversation::query()->create([
                'student_id' => $learner->getKey(), 'course_id' => $course->getKey(), 'lesson_id' => $lesson->getKey(),
                'document_version' => $document->version, 'locale' => $input->locale, 'style_tier' => $tier->value,
                'video_position' => $input->videoPosition, 'status' => 'open',
            ]);

            AiMessage::query()->create([
                'conversation_id' => $conversation->getKey(), 'role' => AiMessage::ROLE_USER, 'content' => $question,
                'guard_flags' => ['mode' => $input->mode->value], 'cache_hit' => false,
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

            AiUsage::query()->create([
                'student_hash' => StudentHash::of((int) $learner->getKey()), 'lesson_id' => $lesson->getKey(),
                'provider' => (string) config('ai.provider', 'unconfigured'), 'model' => $model,
                'input_tokens' => $tokens[0], 'cached_tokens' => $tokens[1], 'output_tokens' => $tokens[2],
                'cost_minor' => $cost, 'latency_ms' => $latency, 'result' => $result,
            ]);

            if ($this->audit->record('ai.assistant_asked', $conversation, (int) $learner->getKey(), null, [
                'lesson_id' => $lesson->getKey(), 'document_version' => $document->version, 'status' => $outcome->value,
                'prompt_version' => $prompt->version, 'quote_verified' => $answered, 'attempts' => $attempts, 'mode' => $input->mode->value,
            ]) === null) {
                throw new RuntimeException('Unable to record assistant audit.');
            }

            return new AssistantReply($outcome, $text, $pages, $language, $answered, (int) $message->getKey(), (int) $conversation->getKey());
        });
    }

    /** @return array<string, mixed> */
    private function schema(): array
    {
        return [
            'type' => 'object',
            'required' => ['status', 'answer', 'source_pages', 'support_quote', 'language'],
            'additionalProperties' => false,
            'properties' => [
                'status' => ['type' => 'string', 'enum' => ['answered', 'not_in_source', 'out_of_scope']],
                'answer' => ['type' => 'string'],
                'source_pages' => ['type' => 'array', 'items' => ['type' => 'integer']],
                'support_quote' => ['type' => 'string'],
                'language' => ['type' => 'string', 'enum' => ['ar', 'en']],
            ],
        ];
    }
}
