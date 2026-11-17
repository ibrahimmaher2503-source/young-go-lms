<?php

declare(strict_types=1);

namespace App\Domain\AI\Admin\Actions;

use App\Domain\AI\Admin\AssistantAdminAccess;
use App\Domain\Audit\AuditLogger;
use App\Domain\Settings\Actions\EnableGatedFlag;
use App\Domain\Settings\PlatformFlag;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Pennant\Feature;
use RuntimeException;

/**
 * The global assistant switch (AI spec 14). Turning it ON goes through the gated-flag action: a written approval reference is
 * kept and the platform gate decides who may (AGENTS rule 3). Turning it OFF is always allowed and takes effect for every
 * learner at once, because eligibility reads the flag on each request. Minors stay behind their own flag (O-015), not here.
 */
final class EnableAssistantGlobally
{
    public function __construct(private readonly EnableGatedFlag $gate, private readonly AuditLogger $audit) {}

    public function __invoke(User $actor, string $approvalReference): void
    {
        if (! AssistantAdminAccess::canConfigure($actor)) {
            throw new AuthorizationException;
        }

        $reference = trim($approvalReference);
        if ($reference === '' || mb_strlen($reference) > 255) {
            throw ValidationException::withMessages(['approval_reference' => __('ai_admin.errors.approval_reference')]);
        }

        DB::transaction(function () use ($actor, $reference): void {
            $this->gate->handle(PlatformFlag::AiAssistant, $actor, $reference);

            if ($this->audit->record('ai.global_enabled', $actor, (int) $actor->getKey(), ['enabled' => false], ['enabled' => true], ['approval_reference' => $reference]) === null) {
                throw new RuntimeException('Unable to record assistant audit.');
            }
        });
    }

    public static function isOn(): bool
    {
        return Feature::for(null)->active(PlatformFlag::AiAssistant->value);
    }
}
