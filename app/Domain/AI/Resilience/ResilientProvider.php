<?php

declare(strict_types=1);

namespace App\Domain\AI\Resilience;

use App\Domain\AI\AiProviderNotConfigured;
use App\Domain\AI\Contracts\AiProviderInterface;
use App\Domain\AI\Dto\AiCompletionRequest;
use App\Domain\AI\Dto\AiCompletionResult;
use App\Domain\AI\Dto\OcrResult;
use App\Domain\AI\Dto\ProviderHealth;
use Throwable;

/**
 * The provider the request pipeline talks to (AI spec 11): the primary route, an optional fallback route, and a circuit breaker
 * on each. A route whose circuit is open is skipped without a call; when no route can answer the caller gets the failure (or
 * AiProviderCircuitOpen) and ends in the safe fallback state, so the lesson player is never affected. A slow answer counts as a
 * failure for the breaker but is still returned when there is no other route, so the pipeline keeps reporting a timeout.
 */
final class ResilientProvider implements AiProviderInterface
{
    public function __construct(
        private readonly AiProviderInterface $primary,
        private readonly ProviderRegistry $registry,
        private readonly CircuitBreaker $breaker,
    ) {}

    public function complete(AiCompletionRequest $request): AiCompletionResult
    {
        $late = null;
        $last = null;

        foreach ($this->routes() as [$key, $provider]) {
            if (! $this->breaker->allows($key)) {
                continue;
            }

            try {
                $result = $provider->complete($request);
            } catch (AiProviderNotConfigured $exception) {
                $last = $exception; // nothing to count: the route does not exist yet (O-014)

                continue;
            } catch (Throwable $exception) {
                $this->breaker->recordFailure($key);
                $last = $exception;

                continue;
            }

            if ($result->latencyMs > $request->timeoutSeconds * 1000) {
                $this->breaker->recordFailure($key);
                $late ??= $result;

                continue;
            }

            $this->breaker->recordSuccess($key);

            return $result;
        }

        return $late ?? throw ($last ?? new AiProviderCircuitOpen);
    }

    public function stream(AiCompletionRequest $request): iterable
    {
        foreach ($this->routes() as [$key, $provider]) {
            if (! $this->breaker->allows($key)) {
                continue;
            }

            try {
                foreach ($provider->stream($request) as $chunk) {
                    yield $chunk;
                }
            } catch (AiProviderNotConfigured $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                $this->breaker->recordFailure($key);

                throw $exception;
            }
            $this->breaker->recordSuccess($key);

            return;
        }

        throw new AiProviderCircuitOpen;
    }

    public function embed(array $texts): array
    {
        return $this->primary->embed($texts);
    }

    public function ocrPage(string $imagePath, array $languages): OcrResult
    {
        return $this->primary->ocrPage($imagePath, $languages);
    }

    public function health(): ProviderHealth
    {
        if ($this->breaker->state($this->registry->activeKey())->isOpen()) {
            return new ProviderHealth(false, 'Circuit open.');
        }

        return $this->primary->health();
    }

    /** @return list<array{0: string, 1: AiProviderInterface}> */
    private function routes(): array
    {
        $routes = [[$this->registry->activeKey(), $this->primary]];
        $fallback = $this->registry->fallbackKey();
        if ($fallback !== null) {
            $routes[] = [$fallback, $this->registry->make($fallback)];
        }

        return $routes;
    }
}
