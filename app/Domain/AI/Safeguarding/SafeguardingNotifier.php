<?php

declare(strict_types=1);

namespace App\Domain\AI\Safeguarding;

use App\Domain\Audit\AuditLogger;
use App\Domain\Notifications\NotificationDispatcher;
use App\Domain\Notifications\Notifications\TemplatedNotice;
use App\Models\AiSafeguardingFlag;
use App\Models\HelpRequest;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Throwable;

/**
 * O-028: tells the safeguarding group about a case. The notice is mandatory (event `safeguarding.alert` is non-opt-out), carries no text
 * from the child and only links to the case, is sent once per case and recipient (dedupe key), and is audited (`safeguarding.notified`).
 * Runs after the case is committed: a failing channel is reported and never undoes the case. WORDING IS DRAFT pending legal review.
 */
final class SafeguardingNotifier
{
    public const string EVENT = 'safeguarding.alert';

    public function __construct(
        private readonly SafeguardingRecipients $recipients,
        private readonly NotificationDispatcher $dispatcher,
        private readonly AuditLogger $audit,
    ) {}

    /** An AI-flagged safeguarding message of a learner who is not a known adult. Returns how many recipients were notified now. */
    public function flagRaised(AiSafeguardingFlag $flag): int
    {
        return $this->notify($flag, 'ai_flag', 'safeguarding-flag:'.$flag->getKey(), url('/admin/help-requests'));
    }

    /** A help request from a minor. Returns how many recipients were notified now. */
    public function minorHelpRequest(HelpRequest $request): int
    {
        return $this->notify($request, 'minor_help_request', 'safeguarding-help:'.$request->getKey(), url('/admin/help-requests/'.$request->getKey()));
    }

    private function notify(Model $case, string $source, string $dedupe, string $link): int
    {
        $sent = 0;
        try {
            $recipients = $this->recipients->all();
            foreach ($recipients as $recipient) {
                try {
                    $sent += $this->dispatcher->send($recipient, new TemplatedNotice(self::EVENT, [], $dedupe, $link)) ? 1 : 0;
                } catch (Throwable $exception) {
                    report($exception);
                }
            }

            // Ids and counts only: never the child's text.
            if ($this->audit->record('safeguarding.notified', $case, null, null, null, [
                'source' => $source, 'case_id' => $case->getKey(), 'recipients' => $recipients->count(), 'sent_now' => $sent,
            ]) === null) {
                report(new RuntimeException('Unable to record safeguarding notice audit.'));
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        return $sent;
    }
}
