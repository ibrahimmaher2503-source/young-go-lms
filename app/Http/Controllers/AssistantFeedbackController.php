<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\AI\Help\Actions\RecordAssistantFeedback;
use App\Domain\AI\Help\HelpLinks;
use App\Domain\AI\Help\HelpRefusedException;
use App\Domain\Identity\ActingLearner;
use App\Models\AiFeedback;
use App\Models\Lesson;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * "Understood" / "still not understood" on one assistant answer (AI spec 12). The learner comes from the session and the
 * lesson from the route; the body names only the answer and the verdict. Repeating a tap is safe.
 */
final class AssistantFeedbackController
{
    public function __construct(private readonly ActingLearner $acting, private readonly RecordAssistantFeedback $feedback) {}

    public function store(Request $request, Lesson $lesson): JsonResponse
    {
        $learner = $this->acting->current();
        abort_if($learner === null, 403);

        $data = $request->validate([
            'message_id' => ['required', 'integer', 'min:1'],
            'type' => ['required', Rule::in([AiFeedback::UNDERSTOOD, AiFeedback::NOT_UNDERSTOOD])],
        ]);

        try {
            $outcome = ($this->feedback)($learner, $lesson, (int) $data['message_id'], (string) $data['type']);
        } catch (HelpRefusedException $refused) {
            $status = match ($refused->reason) {
                HelpRefusedException::NOT_FOUND => 404,
                HelpRefusedException::OPEN_LIMIT, HelpRefusedException::NOT_ESCALATABLE => 409,
                default => 422,
            };

            return $this->json(['state' => $refused->reason, 'message' => $refused->getMessage()], $status);
        } catch (AuthorizationException) {
            return $this->json(['state' => 'forbidden', 'message' => __('ai.unavailable.no_entitlement')], 403);
        }

        if ($outcome->request === null) {
            return $this->json(['state' => 'recorded', 'message' => __('help.panel.thanks')], 200);
        }

        return $this->json([
            'state' => $outcome->created ? 'request_created' : 'request_exists',
            'message' => __($outcome->created ? 'help.panel.created' : 'help.panel.exists'),
            'request_id' => $outcome->request->getKey(),
            'thread_url' => HelpLinks::learner((int) $outcome->request->getKey()),
            'status' => $outcome->request->status->value,
        ], $outcome->created ? 201 : 200);
    }

    /** @param  array<string, mixed>  $body */
    private function json(array $body, int $status): JsonResponse
    {
        return response()->json($body, $status)->header('Cache-Control', 'private, no-store');
    }
}
