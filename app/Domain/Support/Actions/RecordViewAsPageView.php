<?php

declare(strict_types=1);

namespace App\Domain\Support\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\Models\ViewAsSession;

/** Every page shown during a view-as session is audited, with the session as subject (ED-027). */
final class RecordViewAsPageView
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, scalar|null> $context */
    public function __invoke(ViewAsSession $session, string $page, array $context = []): void
    {
        $session->increment('page_views');

        $this->audit->record('support.view_as_page_viewed', $session, $session->staff_id, null, null, [
            'learner_id' => $session->learner_id, 'page' => $page, ...$context,
        ]);
    }
}
