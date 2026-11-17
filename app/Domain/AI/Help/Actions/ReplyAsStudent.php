<?php

declare(strict_types=1);

namespace App\Domain\AI\Help\Actions;

use App\Domain\AI\Help\HelpRefusedException;
use App\Domain\AI\Help\HelpRequestNotices;
use App\Domain\AI\Help\HelpRequestStatus;
use App\Domain\AI\Help\HelpRequestTrail;
use App\Domain\AI\Help\ReplyBody;
use App\Models\HelpRequest;
use App\Models\HelpRequestMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The learner answers inside the thread, only after the instructor wrote (no unrestricted chat), and only on their own
 * request. The request returns to In Progress and the SLA clock restarts; the responsible staff are notified once per reply.
 */
final class ReplyAsStudent
{
    public function __construct(private readonly HelpRequestTrail $trail, private readonly HelpRequestNotices $notices) {}

    public function __invoke(User $learner, HelpRequest $request, string $body): HelpRequestMessage
    {
        if ((int) $request->student_id !== (int) $learner->getKey() || $learner->getAttribute('status') !== 'active') {
            throw new HelpRefusedException(HelpRefusedException::NOT_FOUND);
        }
        $body = ReplyBody::clean($body);

        $message = DB::transaction(function () use ($learner, $request, $body): HelpRequestMessage {
            $locked = HelpRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
            if (! $locked->status->learnerMayReply()) {
                throw new HelpRefusedException($locked->status->isOpen() ? HelpRefusedException::NOT_YOUR_TURN : HelpRefusedException::CLOSED);
            }

            $from = $locked->status;
            $message = HelpRequestMessage::query()->create([
                'help_request_id' => $locked->getKey(), 'author_id' => $learner->getKey(), 'role' => HelpRequestMessage::ROLE_STUDENT, 'body' => $body,
            ]);
            $locked->forceFill([
                'status' => HelpRequestStatus::InProgress, 'overdue_at' => null, 'due_at' => now()->addHours((int) config('ai.help.sla_hours', 24)),
            ])->save();
            $this->trail->record($locked, 'student_replied', $learner, $from, HelpRequestStatus::InProgress, ['message_id' => $message->getKey()]);
            $request->setRawAttributes($locked->getAttributes(), true);

            return $message;
        });

        $this->notices->studentReplied($request->loadMissing(['lesson', 'instructor']), (int) $message->getKey());

        return $message;
    }
}
