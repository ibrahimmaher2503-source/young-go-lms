<?php

declare(strict_types=1);

namespace App\Domain\AI\Resilience;

use App\Domain\AI\Contracts\AiProviderInterface;
use App\Domain\AI\Providers\FakeAiProvider;
use App\Domain\AI\Providers\NullAiProvider;
use App\Domain\Settings\AiSettings;

/**
 * The provider keys the platform knows. No real provider is approved (O-014), so the only keys are the refusing "unconfigured"
 * provider and, in local and testing, the deterministic "fake". A real adapter is added here by T-P7-07 after the owner decides.
 * The choice of a super admin (AiSettings) wins over the environment; with no choice the environment decides, as before.
 */
final class ProviderRegistry
{
    public const string UNCONFIGURED = 'unconfigured';

    public const string FAKE = 'fake';

    public function __construct(private readonly AiSettings $settings) {}

    public static function currentKey(): string
    {
        return app(self::class)->activeKey();
    }

    /** @return list<string> keys an operator may pick */
    public function keys(): array
    {
        return app()->environment('local', 'testing') ? [self::UNCONFIGURED, self::FAKE] : [self::UNCONFIGURED];
    }

    public function activeKey(): string
    {
        $chosen = $this->chosen('provider');
        if ($chosen !== null && in_array($chosen, $this->keys(), true)) {
            return $chosen;
        }

        $configured = config('ai.provider');

        return $configured === self::FAKE || app()->environment('local', 'testing') ? self::FAKE : self::UNCONFIGURED;
    }

    /** The optional second route; null when none is set, it is unknown, or it equals the primary. */
    public function fallbackKey(): ?string
    {
        $key = $this->chosen('fallback_provider');

        return $key !== null && in_array($key, $this->keys(), true) && $key !== $this->activeKey() ? $key : null;
    }

    public function model(): ?string
    {
        return $this->chosen('model');
    }

    public function make(string $key): AiProviderInterface
    {
        return match ($key) {
            self::FAKE => new FakeAiProvider,
            default => new NullAiProvider,
        };
    }

    private function chosen(string $property): ?string
    {
        // Settings that are not migrated yet (fresh install, running migrations) must not break a request: follow the environment.
        $value = rescue(fn (): ?string => match ($property) {
            'provider' => $this->settings->provider,
            'model' => $this->settings->model,
            default => $this->settings->fallback_provider,
        }, null, false);

        return $value === null || $value === '' ? null : $value;
    }
}
