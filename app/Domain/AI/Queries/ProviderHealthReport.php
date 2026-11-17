<?php

declare(strict_types=1);

namespace App\Domain\AI\Queries;

use App\Domain\AI\Resilience\CircuitBreaker;
use App\Domain\AI\Resilience\ProviderRegistry;
use App\Domain\AI\UsageResults;
use App\Models\AiUsage;
use Throwable;

/**
 * The provider health widget (AI spec 11, 13.6): each route (primary, optional fallback) with its circuit state and live health,
 * and the last 24 hours of request outcomes from ai_usage. Counts only; nothing a learner wrote.
 */
final class ProviderHealthReport
{
    public function __construct(private readonly ProviderRegistry $registry, private readonly CircuitBreaker $breaker) {}

    /**
     * @return array{
     *     routes: list<array{role: string, key: string, circuit: string, failures: int, retry_at: int|null, opened_at: int|null, healthy: bool, message: string, latency_ms: int}>,
     *     day: array{requests: int, errors: int, error_rate: float, last_error_at: string|null},
     *     model: string|null
     * }
     */
    public function __invoke(): array
    {
        $routes = [['primary', $this->registry->activeKey()]];
        if (($fallback = $this->registry->fallbackKey()) !== null) {
            $routes[] = ['fallback', $fallback];
        }

        $rows = [];
        foreach ($routes as [$role, $key]) {
            $state = $this->breaker->state($key);
            try {
                $health = $this->registry->make($key)->health();
                [$healthy, $message, $latency] = [$health->healthy, $health->message, $health->latencyMs];
            } catch (Throwable $exception) {
                [$healthy, $message, $latency] = [false, $exception::class, 0];
            }
            $rows[] = [
                'role' => $role, 'key' => $key, 'circuit' => $state->state, 'failures' => $state->failures, 'retry_at' => $state->retryAt,
                'opened_at' => $state->openedAt, 'healthy' => $healthy && ! $state->isOpen(), 'message' => $message, 'latency_ms' => $latency,
            ];
        }

        $day = AiUsage::query()->where('created_at', '>=', now()->subDay());
        $requests = (clone $day)->count();
        $errors = (clone $day)->whereNotIn('result', UsageResults::notErrors())->count();
        $last = (clone $day)->whereNotIn('result', UsageResults::notErrors())->max('created_at');

        return [
            'routes' => $rows,
            'day' => ['requests' => $requests, 'errors' => $errors, 'error_rate' => $requests === 0 ? 0.0 : round($errors * 100 / $requests, 1), 'last_error_at' => $last === null ? null : (string) $last],
            'model' => $this->registry->model(),
        ];
    }
}
