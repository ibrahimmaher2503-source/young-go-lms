<?php

declare(strict_types=1);

namespace App\Domain\AI\Help\Actions;

use App\Domain\AI\Help\HelpRefusedException;
use App\Domain\AI\Help\HelpRequestAccess;
use App\Domain\AI\Help\HelpRequestNotices;
use App\Domain\AI\Help\HelpRequestStatus;
use App\Domain\AI\Help\HelpRequestTrail;
use App\Domain\AI\Help\ReplyBody;
use App\Models\HelpRequest;
use App\Models\HelpRequestMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Staff reply in the request thread. Only staff who may see the request can reply (the assigned instructor, or a manager).
 * The state becomes Instructor Replied, or Waiting for Student when the reply asks for an answer; the SLA clock stops. The
 * learner is notified once per reply.
 */
final class ReplyToHelpRequest
{
    public function __construct(private readonly HelpRequestTrail $trail, private readonly HelpRequestNotices $notices) {}

    public function __invoke(User $staff, HelpRequest $request, string $body, bool $waitForStudent = false): HelpRequestMessage
    {
        HelpRequestAccess::authorize($staff, $request);
        $body = ReplyBody::clean($body);

        $message = DB::transaction(function () use ($staff, $request, $body, $waitForStudent): HelpRequestMessage {
            $locked = HelpRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
            if (! $locked->status->isOpen()) {
                throw new HelpRefusedException(HelpRefusedException::CLOSED);
            }

            $from = $locked->status;
            $to = $waitForStudent ? HelpRequestStatus::WaitingForStudent : HelpRequestStatus::InstructorReplied;
            $message = HelpRequestMessage::query()->create([
                'help_request_id' => $locked->getKey(), 'author_id' => $staff->getKey(), 'role' => HelpRequestMessage::ROLE_INSTRUCTOR, 'body' => $body,
            ]);
            $locked->forceFill(['status' => $to, 'due_at' => null, 'first_replied_at' => $locked->first_replied_at ?? now()])->save();
            $this->trail->record($locked, 'replied', $staff, $from, $to, ['message_id' => $message->getKey()]);
            $request->setRawAttributes($locked->getAttributes(), true);

            return $message;
        });

        $this->notices->replied($request->loadMissing(['student', 'lesson']), (int) $message->getKey());

        return $message;
    }
}
