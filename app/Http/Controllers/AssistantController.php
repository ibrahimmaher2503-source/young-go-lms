<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\AI\Actions\AskLessonAssistant;
use App\Domain\AI\Assistant\AssistantMode;
use App\Domain\AI\Assistant\AssistantOutcome;
use App\Domain\AI\Assistant\AssistantQuestion;
use App\Domain\AI\Assistant\AssistantRefused;
use App\Domain\AI\Assistant\AssistantReply;
use App\Domain\AI\Queries\AssistantStatus;
use App\Domain\AI\UnavailableReason;
use App\Domain\Identity\ActingLearner;
use App\Models\Lesson;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Lesson assistant endpoints (AI spec 17). The lesson comes from the route and the learner from the session; the body can
 * name neither. All rules live in AskLessonAssistant: this class only validates the shape and speaks HTTP and SSE.
 */
final class AssistantController
{
    public function __construct(private readonly ActingLearner $acting, private readonly AskLessonAssistant $ask, private readonly AssistantStatus $status) {}

    public function show(Lesson $lesson): JsonResponse
    {
        $learner = $this->acting->current();
        abort_if($learner === null, 403);

        return $this->json($this->status->for($learner, $lesson), 200);
    }

    public function store(Request $request, Lesson $lesson): Response
    {
        $learner = $this->acting->current();
        abort_if($learner === null, 403);

        $data = $request->validate([
            'mode' => ['nullable', Rule::enum(AssistantMode::class)],
            'question' => [Rule::requiredIf($request->input('mode', 'normal') !== AssistantMode::Simpler->value), 'nullable', 'string', 'max:'.(int) config('ai.question_max_chars', 300)],
            'video_position' => ['nullable', 'integer', 'min:0'],
            'conversation_id' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $reply = ($this->ask)($learner, $lesson, new AssistantQuestion(
                question: (string) ($data['question'] ?? ''),
                locale: app()->getLocale(),
                videoPosition: isset($data['video_position']) ? (int) $data['video_position'] : null,
                conversationId: isset($data['conversation_id']) ? (int) $data['conversation_id'] : null,
                mode: AssistantMode::tryFrom((string) ($data['mode'] ?? 'normal')) ?? AssistantMode::Normal,
            ));
        } catch (AssistantRefused $refused) {
            return $this->refused($refused);
        } catch (AuthorizationException) {
            return $this->json(['state' => 'forbidden', 'message' => __('ai.unavailable.no_entitlement')], 403);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            // The player and the lesson never depend on the assistant: any failure is a plain "temporarily unavailable".
            return $this->json(['state' => AssistantOutcome::Fallback->value, 'message' => __('ai.temporarily_unavailable')], 503);
        }

        if (! $reply->answered()) {
            return $this->json($this->payload($reply), $reply->outcome === AssistantOutcome::Fallback ? 503 : 200);
        }

        return $this->stream($reply);
    }

    /** Token events carry the already validated answer; the final result event carries the structured reply. */
    private function stream(AssistantReply $reply): StreamedResponse
    {
        return response()->stream(function () use ($reply): void {
            foreach (preg_split('/(?<=\s)/u', $reply->message, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $chunk) {
                echo "event: token\ndata: ".json_encode(['text' => $chunk], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
            echo "event: result\ndata: ".json_encode($this->payload($reply), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n\n";
        }, 200, ['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-store, no-transform', 'X-Accel-Buffering' => 'no']);
    }

    /** @return array<string, mixed> */
    private function payload(AssistantReply $reply): array
    {
        return [
            'state' => $reply->outcome->value,
            'message' => $reply->message,
            'message_id' => $reply->messageId,
            'conversation_id' => $reply->conversationId,
            'source_pages' => $reply->sourcePages,
            'language' => $reply->language,
            'quote_verified' => $reply->quoteVerified,
        ];
    }

    private function refused(AssistantRefused $refused): JsonResponse
    {
        $reason = $refused->reason;
        $status = match ($reason) {
            UnavailableReason::QuotaExhausted => 429,
            UnavailableReason::NoApprovedDocument, UnavailableReason::NotRecordedLesson, null => 409,
            default => 403,
        };
        $body = ['state' => $reason->value ?? $refused->kind, 'message' => $refused->getMessage()];
        if ($reason === UnavailableReason::QuotaExhausted) {
            $body['reset_at'] = now()->addDay()->startOfDay()->toIso8601String();
        }

        return $this->json($body, $status);
    }

    /** @param  array<string, mixed>  $body */
    private function json(array $body, int $status): JsonResponse
    {
        return response()->json($body, $status)->header('Cache-Control', 'private, no-store');
    }
}
