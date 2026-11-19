<?php

declare(strict_types=1);

namespace App\Domain\AI\Resilience;

use App\Domain\AI\Contracts\AiProviderInterface;
use App\Domain\AI\Providers\FakeAiProvider;
use App\Domain\AI\Providers\NullAiProvider;
use App\Domain\Settings\AiSettings;
use App\Support\AI\OpenAiProvider;

/**
 * The provider keys the platform knows: the refusing "unconfigured" provider, "openai" (decided 2026-11-18, O-014; inert without
 * an API key and never the default until one is configured) and, in local and testing, the deterministic "fake".
 * The choice of a super admin (AiSettings) wins over the environment; with no choice the environment decides, as before.
 */
final class ProviderRegistry
{
    public const string UNCONFIGURED = 'unconfigured';

    public const string FAKE = 'fake';

    public const string OPENAI = 'openai';

    public function __construct(private readonly AiSettings $settings) {}

    public static function currentKey(): string
    {
        return app(self::class)->activeKey();
    }

    /** @return list<string> keys an operator may pick */
    public function keys(): array
    {
        return app()->environment('local', 'testing') ? [self::UNCONFIGURED, self::OPENAI, self::FAKE] : [self::UNCONFIGURED, self::OPENAI];
    }

    public function activeKey(): string
    {
        $chosen = $this->chosen('provider');
        if ($chosen !== null && in_array($chosen, $this->keys(), true)) {
            return $chosen;
        }

        $configured = config('ai.provider');
        if ($configured === self::OPENAI && self::openAiKeyPresent()) {
            return self::OPENAI;
        }

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
            self::OPENAI => new OpenAiProvider(
                is_string(config('ai.openai.api_key')) ? config('ai.openai.api_key') : null,
                $this->model() ?? (string) config('ai.openai.model', 'gpt-4o-mini'),
                (string) config('ai.openai.base_url', 'https://api.openai.com/v1'),
                (string) config('ai.openai.embedding_model', 'text-embedding-3-small'),
                is_string(config('ai.openai.organization')) ? config('ai.openai.organization') : null,
            ),
            default => new NullAiProvider,
        };
    }

    private static function openAiKeyPresent(): bool
    {
        $key = config('ai.openai.api_key');

        return is_string($key) && trim($key) !== '';
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
