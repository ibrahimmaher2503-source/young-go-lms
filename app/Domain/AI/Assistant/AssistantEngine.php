<?php

declare(strict_types=1);

namespace App\Domain\AI\Assistant;

use App\Domain\AI\AiProviderNotConfigured;
use App\Domain\AI\Contracts\AiProviderInterface;
use App\Domain\AI\Dto\AiCompletionRequest;
use App\Domain\AI\Dto\AiCompletionResult;
use App\Domain\AI\Dto\AiUsage as UsageTokens;
use App\Domain\AI\PromptRenderer;
use App\Domain\AI\Resilience\AiProviderCircuitOpen;
use App\Models\AiPromptVersion;
use Illuminate\Support\Str;
use Throwable;

/**
 * The provider exchange shared by the learner pipeline (AskLessonAssistant), the instructor test console and the evaluation
 * harness: prompt rendering, the provider call, the output guard and its one retry. It persists nothing, checks no
 * eligibility and touches no quota, so the callers decide what is stored. Keeping one copy means the evaluation measures the
 * code learners actually run.
 */
final class AssistantEngine
{
    public function __construct(
        private readonly AiProviderInterface $provider,
        private readonly PromptRenderer $prompts,
        private readonly OutputGuard $guard,
    ) {}

    /**
     * @param  list<array{role: string, content: string}>  $turns
     * @return array{0: AiCompletionRequest, 1: string} the request and the rule text of the prompt (for leak detection)
     */
    public function request(
        AiPromptVersion $prompt,
        StyleTier $tier,
        ReplyLanguage $language,
        string $lessonTitle,
        int $documentVersion,
        AssistantContext $context,
        array $turns,
        string $userHash,
    ): array {
        $system = $this->prompts->render($prompt, $tier->value, $language->label, $lessonTitle, $documentVersion, $context->promptPages, $language->maxWords());
        $rules = Str::beforeLast($system, '<'.config('ai.source_tag', 'lesson_source'));

        return [new AiCompletionRequest(
            promptVersion: $prompt->version,
            cacheablePrefix: $system,
            turns: $turns,
            maxTokens: (int) config('ai.max_output_tokens', 220),
            responseSchema: $this->schema(),
            timeoutSeconds: (int) config('ai.assistant.timeout_seconds', 15),
            userHash: $userHash,
            idempotencyKey: (string) Str::uuid(),
        ), $rules];
    }

    /**
     * Calls the provider and applies the output guard, with one retry when the guard rejects. A provider error or timeout
     * is not retried: it ends in the safe fallback so the learner is never kept waiting.
     *
     * @return array{0: GuardVerdict|null, 1: string, 2: int, 3: AiCompletionResult|null}
     */
    public function exchange(AiCompletionRequest $request, AssistantContext $context, string $rules): array
    {
        $attempts = 0;
        $verdict = null;
        $result = null;
        $tokens = [0, 0, 0];
        $latency = 0;
        $cost = 0;
        $started = hrtime(true);
        $maxAttempts = 1 + max(0, (int) config('ai.assistant.retries', 1));

        while ($attempts < $maxAttempts) {
            $remaining = $request->timeoutSeconds - (int) floor((hrtime(true) - $started) / 1e9);
            if ($attempts > 0 && $remaining <= 0) {
                return [null, 'timeout', $attempts, $this->usage($result, $tokens, $latency, $cost)];
            }
            $attempts++;
            try {
                $result = $this->provider->complete($attempts === 1 ? $request : new AiCompletionRequest(
                    $request->promptVersion, $request->cacheablePrefix, $request->turns, $request->maxTokens, $request->responseSchema,
                    $remaining, $request->userHash, $request->idempotencyKey.':retry',
                ));
            } catch (Throwable $exception) {
                if (! $exception instanceof AiProviderNotConfigured && ! $exception instanceof AiProviderCircuitOpen) {
                    report($exception);
                }

                return [null, 'provider_error', $attempts, $this->usage($result, $tokens, $latency, $cost)];
            }

            $tokens = [$tokens[0] + $result->usage->inputTokens, $tokens[1] + $result->usage->cachedTokens, $tokens[2] + $result->usage->outputTokens];
            $latency += $result->latencyMs;
            $cost += $result->costMinor;
            if (max($latency, (hrtime(true) - $started) / 1e6) > $request->timeoutSeconds * 1000) {
                return [null, 'timeout', $attempts, $this->usage($result, $tokens, $latency, $cost)];
            }

            $verdict = $this->guard->inspect($result->content, $context, $rules);
            if ($verdict->passed()) {
                break;
            }
        }

        return [$verdict, 'guard', $attempts, $this->usage($result, $tokens, $latency, $cost)];
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $tokens
     */
    private function usage(?AiCompletionResult $last, array $tokens, int $latency, int $cost): ?AiCompletionResult
    {
        return $last === null ? null : new AiCompletionResult(
            $last->content, new UsageTokens($tokens[0], $tokens[1], $tokens[2]), $cost, $latency,
            $last->providerRequestId, $last->finishReason, $last->model,
        );
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
