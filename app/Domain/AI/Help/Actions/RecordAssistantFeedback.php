<?php

declare(strict_types=1);

namespace App\Domain\AI\Help\Actions;

use App\Domain\AI\Assistant\AssistantOutcome;
use App\Domain\AI\Help\FeedbackOutcome;
use App\Domain\AI\Help\HelpRefusedException;
use App\Domain\AI\Help\HelpRequestNotices;
use App\Domain\AI\Help\HelpRequestStatus;
use App\Domain\AI\Help\HelpRequestTrail;
use App\Domain\AI\Help\HelpRouting;
use App\Domain\Audit\AuditLogger;
use App\Models\AiConversation;
use App\Models\AiFeedback;
use App\Models\AiMessage;
use App\Models\HelpRequest;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The learner's verdict on one assistant answer (AI spec 12). "Understood" is recorded and closes the matter. "Not
 * understood" creates one help request for that answer, routed to the responsible instructor (or the admin queue), and
 * repeated taps return the same request. At most N requests stay open per learner and lesson. The answer must belong to the
 * learner and the lesson in the route; the assistant being switched off later does not stop a learner from asking a person.
 */
final class RecordAssistantFeedback
{
    public function __construct(private readonly AuditLogger $audit, private readonly HelpRequestTrail $trail, private readonly HelpRequestNotices $notices) {}

    public function __invoke(User $learner, Lesson $lesson, int $messageId, string $type): FeedbackOutcome
    {
        if ($learner->getAttribute('status') !== 'active' || ! $learner->can('ai.ask')) {
            throw new AuthorizationException;
        }
        if (! in_array($type, [AiFeedback::UNDERSTOOD, AiFeedback::NOT_UNDERSTOOD], true)) {
            throw new HelpRefusedException(HelpRefusedException::INVALID_TYPE);
        }

        [$message, $conversation] = $this->answer($learner, $lesson, $messageId);

        if ($type === AiFeedback::UNDERSTOOD) {
            $this->feedback($learner, $message, $conversation, $type);

            return new FeedbackOutcome($type);
        }

        $existing = HelpRequest::query()->where('ai_message_id', $message->getKey())->first();
        if ($existing !== null) {
            return new FeedbackOutcome($type, $existing, false);
        }
        // Answers the guards produced (no model answer) are not escalated: they carry no explanation to clarify.
        if (($message->guard_flags['guard'] ?? null) !== null || $message->status === null) {
            throw new HelpRefusedException(HelpRefusedException::NOT_ESCALATABLE);
        }

        try {
            $request = DB::transaction(fn (): HelpRequest => $this->create($learner, $lesson, $message, $conversation));
        } catch (UniqueConstraintViolationException) {
            // A parallel tap won the race: same request.
            $found = HelpRequest::query()->where('ai_message_id', $message->getKey())->firstOrFail();

            return new FeedbackOutcome($type, $found, false);
        }

        if ($request->wasRecentlyCreated) {
            $this->notices->created($request->loadMissing(['lesson', 'instructor']));
        }

        return new FeedbackOutcome($type, $request, $request->wasRecentlyCreated);
    }

    /** @return array{0: AiMessage, 1: AiConversation} */
    private function answer(User $learner, Lesson $lesson, int $messageId): array
    {
        $message = AiMessage::query()->whereKey($messageId)->where('role', AiMessage::ROLE_ASSISTANT)->first();
        $conversation = $message === null ? null : AiConversation::query()->find($message->conversation_id);
        if ($message === null || $conversation === null || (int) $conversation->student_id !== (int) $learner->getKey() || (int) $conversation->lesson_id !== (int) $lesson->getKey()) {
            throw new HelpRefusedException(HelpRefusedException::NOT_FOUND);
        }

        return [$message, $conversation];
    }

    private function feedback(User $learner, AiMessage $message, AiConversation $conversation, string $type): void
    {
        DB::transaction(function () use ($learner, $message, $conversation, $type): void {
            $row = AiFeedback::query()->createOrFirst(['message_id' => $message->getKey(), 'type' => $type]);
            if (! $row->wasRecentlyCreated) {
                return;
            }
            if ($this->audit->record('ai.feedback_recorded', $conversation, (int) $learner->getKey(), null, ['type' => $type, 'message_id' => $message->getKey()], ['lesson_id' => $conversation->lesson_id]) === null) {
                throw new RuntimeException('Unable to record assistant feedback audit.');
            }
        });
    }

    private function create(User $learner, Lesson $lesson, AiMessage $message, AiConversation $conversation): HelpRequest
    {
        // Serialises one learner's requests, so the open-request limit holds under parallel taps.
        User::query()->whereKey($learner->getKey())->lockForUpdate()->first();

        $existing = HelpRequest::query()->where('ai_message_id', $message->getKey())->first();
        if ($existing !== null) {
            return $existing;
        }
        $open = HelpRequest::query()->where('student_id', $learner->getKey())->where('lesson_id', $lesson->getKey())
            ->whereIn('status', HelpRequestStatus::openValues())->count();
        if ($open >= (int) config('ai.help.max_open_per_lesson', 3)) {
            throw new HelpRefusedException(HelpRefusedException::OPEN_LIMIT);
        }

        $course = $lesson->item->course ?? throw new HelpRefusedException(HelpRefusedException::NOT_FOUND);
        $instructor = HelpRouting::instructorFor($course);
        $question = AiMessage::query()->where('conversation_id', $conversation->getKey())->where('role', AiMessage::ROLE_USER)
            ->where('id', '<', $message->getKey())->orderByDesc('id')->value('content');

        $request = HelpRequest::query()->create([
            'student_id' => $learner->getKey(), 'course_id' => $conversation->course_id, 'lesson_id' => $lesson->getKey(),
            'assigned_instructor_id' => $instructor?->getKey(), 'conversation_id' => $conversation->getKey(), 'ai_message_id' => $message->getKey(),
            'status' => HelpRequestStatus::New, 'locale' => $conversation->locale, 'video_position' => $conversation->video_position,
            'document_version' => $conversation->document_version, 'ai_status' => $message->status ?? AssistantOutcome::Answered->value,
            'source_pages' => $message->source_pages, 'question' => (string) $question, 'ai_answer' => $message->content,
            'due_at' => now()->addHours((int) config('ai.help.sla_hours', 24)),
        ]);
        AiFeedback::query()->createOrFirst(['message_id' => $message->getKey(), 'type' => AiFeedback::NOT_UNDERSTOOD]);
        $this->trail->record($request, 'created', $learner, null, HelpRequestStatus::New, [
            'assigned_instructor_id' => $instructor?->getKey(), 'routed_to' => $instructor === null ? 'admin_queue' : 'instructor',
        ]);

        return $request;
    }
}
