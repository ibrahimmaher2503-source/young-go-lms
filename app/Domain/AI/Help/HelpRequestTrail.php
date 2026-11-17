<?php

declare(strict_types=1);

namespace App\Domain\AI\Help;

use App\Domain\Audit\AuditLogger;
use App\Models\HelpRequest;
use App\Models\HelpRequestEvent;
use App\Models\User;
use RuntimeException;

/** Writes the event row (actor, state change, time) and the audit entry of every help request change. Audit carries ids only, never the question or answer. */
final class HelpRequestTrail
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param  array<string, mixed>  $context */
    public function record(HelpRequest $request, string $event, ?User $actor, ?HelpRequestStatus $from, ?HelpRequestStatus $to, array $context = []): void
    {
        HelpRequestEvent::query()->create([
            'help_request_id' => $request->getKey(), 'actor_id' => $actor?->getKey(), 'event' => $event,
            'from_status' => $from?->value, 'to_status' => $to?->value,
        ]);

        $recorded = $this->audit->record(
            'help_request.'.$event, $request, $actor === null ? null : (int) $actor->getKey(),
            $from === null ? null : ['status' => $from->value], $to === null ? null : ['status' => $to->value],
            ['course_id' => $request->course_id, 'lesson_id' => $request->lesson_id, 'student_id' => $request->student_id] + $context,
        );
        if ($recorded === null) {
            throw new RuntimeException('Unable to record help request audit.');
        }
    }
}
