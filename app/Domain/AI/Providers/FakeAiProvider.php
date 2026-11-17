<?php

declare(strict_types=1);

namespace App\Domain\AI\Providers;

use App\Domain\AI\Contracts\AiProviderInterface;
use App\Domain\AI\Dto\AiCompletionRequest;
use App\Domain\AI\Dto\AiCompletionResult;
use App\Domain\AI\Dto\AiUsage;
use App\Domain\AI\Dto\OcrResult;
use App\Domain\AI\Dto\ProviderHealth;
use Closure;
use Throwable;

/**
 * Deterministic stand-in used by every test and by local development: no network, no cost, same input same output.
 * Queue answers with respondWith(), force an error with failNext(), and read back what was asked with requests().
 */
final class FakeAiProvider implements AiProviderInterface
{
    public const string DEFAULT_ANSWER = '{"status":"not_in_source","answer":"","pages":[]}';

    /** @var list<string|array<string, mixed>|Closure(AiCompletionRequest): string> */
    private array $queue = [];

    /** @var list<Throwable> */
    private array $failures = [];

    /** @var list<AiCompletionRequest> */
    private array $requests = [];

    private bool $healthy = true;

    /** @param string|array<string, mixed>|Closure(AiCompletionRequest): string $answer used once, in order */
    public function respondWith(string|array|Closure $answer): self
    {
        $this->queue[] = $answer;

        return $this;
    }

    public function failNext(Throwable $exception): self
    {
        $this->failures[] = $exception;

        return $this;
    }

    public function setHealthy(bool $healthy): self
    {
        $this->healthy = $healthy;

        return $this;
    }

    /** @return list<AiCompletionRequest> */
    public function requests(): array
    {
        return $this->requests;
    }

    public function reset(): self
    {
        $this->queue = $this->failures = $this->requests = [];
        $this->healthy = true;

        return $this;
    }

    public function complete(AiCompletionRequest $request): AiCompletionResult
    {
        $this->requests[] = $request;
        if ($this->failures !== []) {
            throw array_shift($this->failures);
        }

        $next = $this->queue === [] ? self::DEFAULT_ANSWER : array_shift($this->queue);
        $content = match (true) {
            $next instanceof Closure => $next($request),
            is_array($next) => (string) json_encode($next, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            default => $next,
        };

        $input = (int) ceil((strlen($request->cacheablePrefix) + array_sum(array_map(static fn (array $turn): int => strlen($turn['content']), $request->turns))) / 4);

        return new AiCompletionResult(
            content: $content,
            usage: new AiUsage($input, 0, (int) ceil(strlen($content) / 4)),
            costMinor: 0,
            latencyMs: 1,
            providerRequestId: 'fake-'.substr(hash('sha256', $request->idempotencyKey), 0, 12),
            finishReason: 'stop',
            model: 'fake-model',
        );
    }

    public function stream(AiCompletionRequest $request): iterable
    {
        foreach (preg_split('/(?<=\s)/u', $this->complete($request)->content) ?: [] as $chunk) {
            yield $chunk;
        }
    }

    public function embed(array $texts): array
    {
        return array_map(static function (string $text): array {
            $hash = hash('sha256', $text, true);

            return array_map(static fn (int $i): float => ord($hash[$i]) / 255, range(0, 7));
        }, $texts);
    }

    public function ocrPage(string $imagePath, array $languages): OcrResult
    {
        return new OcrResult('FAKE OCR TEXT', 0.9);
    }

    public function health(): ProviderHealth
    {
        return new ProviderHealth($this->healthy, $this->healthy ? 'fake provider' : 'fake provider set to unhealthy', 1);
    }
}
