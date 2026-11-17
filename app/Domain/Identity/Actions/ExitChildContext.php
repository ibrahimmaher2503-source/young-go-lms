<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\ActingLearner;
use App\Models\User;

final class ExitChildContext
{
    public function __construct(private readonly AuditLogger $audit, private readonly ActingLearner $acting) {}

    public function handle(User $parent): void
    {
        $child = $this->acting->isParentContext() ? $this->acting->current() : null;
        $this->acting->exit();

        if ($child !== null) {
            $this->audit->record('guardian.context_exited', $child, (int) $parent->getKey());
        }
    }
}
