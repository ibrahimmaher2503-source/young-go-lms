<?php

declare(strict_types=1);

namespace App\Domain\AI\Providers;

use App\Domain\AI\AiProviderNotConfigured;
use App\Domain\AI\Contracts\AiProviderInterface;
use App\Domain\AI\Dto\AiCompletionRequest;
use App\Domain\AI\Dto\AiCompletionResult;
use App\Domain\AI\Dto\OcrResult;
use App\Domain\AI\Dto\ProviderHealth;

/** The production default until a provider is approved: it never answers, it refuses loudly and reports itself down. */
final class NullAiProvider implements AiProviderInterface
{
    public function complete(AiCompletionRequest $request): AiCompletionResult
    {
        throw new AiProviderNotConfigured;
    }

    public function stream(AiCompletionRequest $request): iterable
    {
        throw new AiProviderNotConfigured;
    }

    public function embed(array $texts): array
    {
        throw new AiProviderNotConfigured;
    }

    public function ocrPage(string $imagePath, array $languages): OcrResult
    {
        throw new AiProviderNotConfigured;
    }

    public function health(): ProviderHealth
    {
        return new ProviderHealth(false, 'No provider configured (O-014).');
    }
}
