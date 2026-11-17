<?php

declare(strict_types=1);

namespace App\Domain\AI\Help;

use App\Domain\AI\Help\Notifications\HelpRequestNotice;
use App\Domain\Notifications\NotificationDispatcher;
use App\Models\HelpRequest;
use App\Models\User;
use Illuminate\Support\Collection;
use Throwable;

/** Sends help request notices after the change is committed. A failing channel is reported and never undoes the change. */
final class HelpRequestNotices
{
    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    public function created(HelpRequest $request): void
    {
        $this->toStaff($request, HelpRequestNotice::CREATED, 'help-request-new:'.$request->getKey(), HelpRouting::responders($request));
    }

    public function studentReplied(HelpRequest $request, int $messageId): void
    {
        $this->toStaff($request, HelpRequestNotice::STUDENT_REPLIED, 'help-request-student-reply:'.$messageId, HelpRouting::responders($request));
    }

    /** The assigned instructor (if any) and every manager hear about an overdue request, once per overdue cycle. */
    public function overdue(HelpRequest $request): void
    {
        $recipients = HelpRouting::managers();
        if ($request->instructor !== null) {
            $recipients = $recipients->push($request->instructor)->unique(static fn ($user): int => (int) $user->getKey());
        }
        $this->toStaff($request, HelpRequestNotice::OVERDUE, 'help-request-overdue:'.$request->getKey().':'.$request->due_at->timestamp, $recipients);
    }

    /** One notice per instructor reply (the key holds the reply id), routed to guardians for a child without email. */
    public function replied(HelpRequest $request, int $messageId): void
    {
        $student = $request->student;
        try {
            $this->dispatcher->send($student, new HelpRequestNotice(HelpRequestNotice::REPLY, (int) $request->getKey(), $this->lesson($request), 'help-request-reply:'.$messageId));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /** @param  Collection<int, User>  $recipients */
    private function toStaff(HelpRequest $request, string $kind, string $key, Collection $recipients): void
    {
        $title = $this->lesson($request);
        foreach ($recipients as $recipient) {
            try {
                $this->dispatcher->send($recipient, new HelpRequestNotice($kind, (int) $request->getKey(), $title, $key));
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    private function lesson(HelpRequest $request): string
    {
        return (string) $request->lesson->title;
    }
}
