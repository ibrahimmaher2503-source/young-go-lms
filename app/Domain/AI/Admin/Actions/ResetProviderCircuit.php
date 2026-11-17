<?php

declare(strict_types=1);

namespace App\Domain\AI\Admin\Actions;

use App\Domain\AI\Admin\AssistantAdminAccess;
use App\Domain\AI\Resilience\CircuitBreaker;
use App\Domain\AI\Resilience\ProviderRegistry;
use App\Domain\Audit\AuditLogger;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Lets a super admin close a circuit by hand once the provider is known to be back (AI spec 11). */
final class ResetProviderCircuit
{
    public function __construct(private readonly CircuitBreaker $breaker, private readonly ProviderRegistry $registry, private readonly AuditLogger $audit) {}

    public function __invoke(User $actor, string $route): void
    {
        if (! AssistantAdminAccess::canConfigure($actor)) {
            throw new AuthorizationException;
        }

        if (! in_array($route, $this->registry->keys(), true)) {
            throw ValidationException::withMessages(['route' => __('ai_admin.errors.unknown_route')]);
        }

        $before = $this->breaker->state($route);
        $this->breaker->reset($route);

        if ($this->audit->record('ai.circuit_reset', $actor, (int) $actor->getKey(), ['state' => $before->state], ['state' => 'closed'], ['route' => $route]) === null) {
            throw new RuntimeException('Unable to record assistant audit.');
        }
    }
}
