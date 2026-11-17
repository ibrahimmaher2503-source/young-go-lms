<?php

declare(strict_types=1);

namespace App\Domain\AI\Help\Notifications;

use App\Domain\AI\Help\HelpLinks;
use App\Domain\Notifications\LocalizedNotification;
use App\Models\User;

/**
 * One notice about a help request. Kinds: created, student_replied and overdue go to staff; reply goes to the learner (and,
 * through the router, to guardians of a child without email). The text never contains the question, the answer or the reply:
 * only the lesson title, so a guardian email copy leaks nothing private. The dedupe key makes each event notify once.
 */
final class HelpRequestNotice extends LocalizedNotification
{
    public const string CREATED = 'created';

    public const string STUDENT_REPLIED = 'student_replied';

    public const string OVERDUE = 'overdue';

    public const string REPLY = 'reply';

    public function __construct(private readonly string $kind, private readonly int $requestId, private readonly string $lessonTitle, private readonly string $dedupe) {}

    public function dedupeKey(): string
    {
        return $this->dedupe;
    }

    protected function title(User $recipient): string
    {
        return __('help.notice.'.$this->kind.'.title', ['lesson' => $this->lessonTitle]);
    }

    protected function body(User $recipient): string
    {
        return __('help.notice.'.$this->kind.'.body', ['lesson' => $this->lessonTitle]);
    }

    protected function url(User $recipient): string
    {
        return $this->kind === self::REPLY ? HelpLinks::learner($this->requestId) : HelpLinks::staff($recipient, $this->requestId);
    }
}
