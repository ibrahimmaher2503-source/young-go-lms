<?php

declare(strict_types=1);

namespace App\Domain\AI\Admin\Actions;

use App\Domain\AI\Admin\AssistantAdminAccess;
use App\Domain\Audit\AuditLogger;
use App\Domain\Settings\PlatformFlag;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;
use RuntimeException;

/** Kill switch: the assistant is off for everyone immediately; lessons, PDFs and progress are unaffected. */
final class DisableAssistantGlobally
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $actor): void
    {
        if (! AssistantAdminAccess::canConfigure($actor)) {
            throw new AuthorizationException;
        }

        DB::transaction(function () use ($actor): void {
            $was = Feature::for(null)->active(PlatformFlag::AiAssistant->value);
            Feature::for(null)->deactivate(PlatformFlag::AiAssistant->value);

            if ($this->audit->record('ai.global_disabled', $actor, (int) $actor->getKey(), ['enabled' => $was], ['enabled' => false]) === null) {
                throw new RuntimeException('Unable to record assistant audit.');
            }
        });
    }
}
