<?php

declare(strict_types=1);

namespace App\Domain\AI\Contracts;

use App\Domain\AI\Dto\AiCompletionRequest;
use App\Domain\AI\Dto\AiCompletionResult;
use App\Domain\AI\Dto\OcrResult;
use App\Domain\AI\Dto\ProviderHealth;

/**
 * Everything the assistant needs from a model provider (AI spec section 11). Provider and model stay OPEN (O-014):
 * the course engine only ever sees this contract, and tests only ever see the fake.
 */
interface AiProviderInterface
{
    /** Non-streaming completion. Network adapters must set a total transport timeout from the request. */
    public function complete(AiCompletionRequest $request): AiCompletionResult;

    /**
     * Token chunks of the same answer, in order.
     *
     * @return iterable<string>
     */
    public function stream(AiCompletionRequest $request): iterable;

    /**
     * Embeddings for chunked mode only (large PDFs).
     *
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embed(array $texts): array;

    /**
     * Reads one rendered page image for the ingestion fallback.
     *
     * @param  list<string>  $languages
     */
    public function ocrPage(string $imagePath, array $languages): OcrResult;

    public function health(): ProviderHealth;
}
