<?php

declare(strict_types=1);

namespace App\Domain\AI\Evaluation;

use App\Domain\AI\Contracts\AiProviderInterface;
use App\Domain\AI\Dto\AiCompletionRequest;
use App\Domain\AI\Dto\AiCompletionResult;
use App\Domain\AI\Dto\OcrResult;
use App\Domain\AI\Dto\ProviderHealth;

/** Wraps a provider and remembers what was sent and how long each answer took, so the harness can check the prompt itself. */
final class RecordingProvider implements AiProviderInterface
{
    /** @var list<AiCompletionRequest> */
    private array $requests = [];

    /** @var list<int> */
    private array $latencies = [];

    public function __construct(private readonly AiProviderInterface $inner) {}

    /** @return list<AiCompletionRequest> */
    public function requests(): array
    {
        return $this->requests;
    }

    /** @return list<int> */
    public function latencies(): array
    {
        return $this->latencies;
    }

    public function reset(): void
    {
        $this->requests = $this->latencies = [];
    }

    public function complete(AiCompletionRequest $request): AiCompletionResult
    {
        $this->requests[] = $request;
        $result = $this->inner->complete($request);
        $this->latencies[] = $result->latencyMs;

        return $result;
    }

    public function stream(AiCompletionRequest $request): iterable
    {
        return $this->inner->stream($request);
    }

    public function embed(array $texts): array
    {
        return $this->inner->embed($texts);
    }

    public function ocrPage(string $imagePath, array $languages): OcrResult
    {
        return $this->inner->ocrPage($imagePath, $languages);
    }

    public function health(): ProviderHealth
    {
        return $this->inner->health();
    }
}
