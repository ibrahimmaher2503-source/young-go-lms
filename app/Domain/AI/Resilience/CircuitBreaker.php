<?php

declare(strict_types=1);

namespace App\Domain\AI\Resilience;

use App\Domain\Settings\AiSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Per-route circuit breaker (AI spec 11): N consecutive failures inside a window mark the route down for a cool-off period.
 * After the cool-off exactly one trial request is let through (half open); its success closes the circuit, its failure re-opens it.
 * State lives in the shared cache so every worker sees it. It never stores a question, an answer or a student.
 */
final class CircuitBreaker
{
    public function __construct(private readonly AiSettings $settings) {}

    /** May a request be sent to this route now? */
    public function allows(string $route): bool
    {
        $until = $this->openUntil($route);
        if ($until === null) {
            return true;
        }

        if (now()->getTimestamp() < $until) {
            return false;
        }

        // Cool-off is over: one caller gets the trial, everyone else keeps waiting for its verdict.
        return Cache::add($this->key($route, 'trial'), true, max(1, $this->settings->breaker_cool_off_seconds));
    }

    public function recordSuccess(string $route): void
    {
        Cache::forget($this->key($route, 'failures'));
        Cache::forget($this->key($route, 'open_until'));
        Cache::forget($this->key($route, 'trial'));
    }

    public function recordFailure(string $route): void
    {
        $failuresKey = $this->key($route, 'failures');
        Cache::add($failuresKey, 0, max(1, $this->settings->breaker_window_seconds));
        $count = (int) Cache::increment($failuresKey);

        // A failed half-open trial re-opens immediately; otherwise the threshold decides.
        $trial = Cache::has($this->key($route, 'trial'));
        if ($count >= max(1, $this->settings->breaker_failures) || $trial) {
            $this->open($route);
        }
    }

    public function reset(string $route): void
    {
        $this->recordSuccess($route);
        Cache::forget($this->key($route, 'opened_at'));
    }

    public function state(string $route): CircuitState
    {
        $failures = (int) Cache::get($this->key($route, 'failures'), 0);
        $until = $this->openUntil($route);
        $openedAt = Cache::get($this->key($route, 'opened_at'));
        $opened = is_numeric($openedAt) ? (int) $openedAt : null;

        if ($until === null) {
            return new CircuitState(CircuitState::CLOSED, $failures, null, $opened);
        }

        return new CircuitState(now()->getTimestamp() < $until ? CircuitState::OPEN : CircuitState::HALF_OPEN, $failures, $until, $opened);
    }

    private function open(string $route): void
    {
        $until = now()->getTimestamp() + max(1, $this->settings->breaker_cool_off_seconds);
        Cache::put($this->key($route, 'open_until'), $until, now()->addSeconds(max(1, $this->settings->breaker_cool_off_seconds) + 3600));
        Cache::put($this->key($route, 'opened_at'), now()->getTimestamp(), now()->addDay());
        Cache::forget($this->key($route, 'failures'));
        Cache::forget($this->key($route, 'trial'));

        try {
            Log::warning('AI provider circuit opened.', ['route' => $route, 'cool_off_seconds' => $this->settings->breaker_cool_off_seconds]);
        } catch (Throwable) {
            // Logging must never break a learner request.
        }
    }

    private function openUntil(string $route): ?int
    {
        $value = Cache::get($this->key($route, 'open_until'));

        return is_numeric($value) ? (int) $value : null;
    }

    private function key(string $route, string $part): string
    {
        return 'ai:breaker:'.$route.':'.$part;
    }
}
