<?php

declare(strict_types=1);

namespace App\Domain\AI\Admin\Actions;

use App\Domain\AI\Admin\AssistantAdminAccess;
use App\Domain\AI\Resilience\ProviderRegistry;
use App\Domain\Audit\AuditLogger;
use App\Domain\Settings\AiSettings;
use App\Models\AiPromptVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Provider, model, fallback provider, active prompt version and circuit-breaker limits (AI spec 11, 14). Only a super admin
 * (ai.configure). Only keys the registry knows can be chosen: with no real provider approved (O-014) that is the refusing
 * "unconfigured" provider (and the fake in local and test runs), so this screen cannot switch on a provider that does not
 * exist. The model name is stored for the future adapter to read; it is never a secret. Credentials never live here.
 */
final class SaveAssistantProvider
{
    public function __construct(private readonly AiSettings $settings, private readonly ProviderRegistry $registry, private readonly AuditLogger $audit) {}

    /** @param  array<string, mixed>  $data */
    public function __invoke(User $actor, array $data): void
    {
        if (! AssistantAdminAccess::canConfigure($actor)) {
            throw new AuthorizationException;
        }

        $keys = $this->registry->keys();
        $clean = Validator::make($data, [
            'provider' => ['required', 'string', Rule::in($keys)],
            'fallback_provider' => ['nullable', 'string', Rule::in($keys), 'different:provider'],
            'model' => ['nullable', 'string', 'max:80'],
            'prompt_version' => ['required', 'integer', Rule::exists('ai_prompt_versions', 'version')],
            'breaker_failures' => ['required', 'integer', 'min:1', 'max:100'],
            'breaker_window_seconds' => ['required', 'integer', 'min:10', 'max:3600'],
            'breaker_cool_off_seconds' => ['required', 'integer', 'min:5', 'max:3600'],
        ], [], (array) __('ai_admin.fields'))->validate();

        $before = $this->snapshot();

        DB::transaction(function () use ($actor, $clean, $before): void {
            $this->settings->provider = (string) $clean['provider'];
            $this->settings->fallback_provider = filled($clean['fallback_provider'] ?? null) ? (string) $clean['fallback_provider'] : null;
            $this->settings->model = filled($clean['model'] ?? null) ? trim((string) $clean['model']) : null;
            $this->settings->breaker_failures = (int) $clean['breaker_failures'];
            $this->settings->breaker_window_seconds = (int) $clean['breaker_window_seconds'];
            $this->settings->breaker_cool_off_seconds = (int) $clean['breaker_cool_off_seconds'];
            $this->settings->save();

            $version = (int) $clean['prompt_version'];
            if (AiPromptVersion::current()?->version !== $version) {
                AiPromptVersion::query()->where('active', true)->update(['active' => false]);
                AiPromptVersion::query()->where('version', $version)->update(['active' => true]);
            }

            if ($this->audit->record('ai.provider_saved', $actor, (int) $actor->getKey(), $before, $this->snapshot()) === null) {
                throw new RuntimeException('Unable to record assistant audit.');
            }
        });
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        return [
            'provider' => $this->settings->provider, 'fallback_provider' => $this->settings->fallback_provider, 'model' => $this->settings->model,
            'prompt_version' => AiPromptVersion::current()?->version,
            'breaker_failures' => $this->settings->breaker_failures, 'breaker_window_seconds' => $this->settings->breaker_window_seconds,
            'breaker_cool_off_seconds' => $this->settings->breaker_cool_off_seconds,
        ];
    }
}
