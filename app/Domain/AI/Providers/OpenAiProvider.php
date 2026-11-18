<?php

declare(strict_types=1);

namespace App\Domain\AI\Providers;

use App\Domain\AI\AiProviderNotConfigured;
use App\Domain\AI\Contracts\AiProviderInterface;
use App\Domain\AI\Dto\AiCompletionRequest;
use App\Domain\AI\Dto\AiCompletionResult;
use App\Domain\AI\Dto\AiUsage;
use App\Domain\AI\Dto\OcrResult;
use App\Domain\AI\Dto\ProviderHealth;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * OpenAI adapter (O-014, decided 2026-11-18). Talks to the Chat Completions and Embeddings endpoints through the Laravel Http
 * facade only; no SDK. It is inert: without `ai.openai.api_key` every call refuses with AiProviderNotConfigured (which the circuit
 * breaker does not count) and health() reports down. Failures throw RuntimeException so ResilientProvider counts them.
 * Minors never reach it: AssistantEligibility refuses a non-adult while this provider is active unless `ai.openai.allow_minors`
 * is switched on after legal approval. Requests carry only the opaque student hash as `user`, never an identity; nothing is logged.
 * The data region and terms are still to be confirmed with legal before real learner data is sent. Tests use Http::fake or FakeAiProvider.
 */
final class OpenAiProvider implements AiProviderInterface
{
    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $model,
        private readonly string $baseUrl = 'https://api.openai.com/v1',
        private readonly string $embeddingModel = 'text-embedding-3-small',
        private readonly ?string $organization = null,
    ) {}

    public function complete(AiCompletionRequest $request): AiCompletionResult
    {
        $started = hrtime(true);
        $messages = [['role' => 'system', 'content' => $request->cacheablePrefix]];
        foreach ($request->turns as $turn) {
            $messages[] = ['role' => $turn['role'], 'content' => $turn['content']];
        }

        $payload = [
            'model' => $this->model, 'messages' => $messages, 'max_completion_tokens' => $request->maxTokens, 'user' => $request->userHash,
        ];
        if ($request->responseSchema !== []) {
            $payload['response_format'] = ['type' => 'json_schema', 'json_schema' => ['name' => 'assistant_reply', 'strict' => false, 'schema' => $request->responseSchema]];
        }

        $body = $this->post('/chat/completions', $payload, max(1, $request->timeoutSeconds));
        $content = $body['choices'][0]['message']['content'] ?? null;
        if (! is_string($content)) {
            throw new RuntimeException('OpenAI returned no message content.');
        }

        $usage = is_array($body['usage'] ?? null) ? $body['usage'] : [];
        $input = (int) ($usage['prompt_tokens'] ?? 0);
        $cached = (int) ($usage['prompt_tokens_details']['cached_tokens'] ?? 0);
        $output = (int) ($usage['completion_tokens'] ?? 0);

        return new AiCompletionResult(
            $content, new AiUsage($input, $cached, $output), $this->costMinor($input, $cached, $output), (int) ((hrtime(true) - $started) / 1_000_000),
            (string) ($body['id'] ?? ''), (string) ($body['choices'][0]['finish_reason'] ?? 'stop'), (string) ($body['model'] ?? $this->model),
        );
    }

    /** One chunk with the whole answer: the pipeline verifies a complete answer before showing anything, so token streaming adds nothing yet. */
    public function stream(AiCompletionRequest $request): iterable
    {
        yield $this->complete($request)->content;
    }

    public function embed(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        $body = $this->post('/embeddings', ['model' => $this->embeddingModel, 'input' => $texts], 30);
        $vectors = [];
        foreach ((array) ($body['data'] ?? []) as $row) {
            $vectors[] = array_map(static fn ($v): float => (float) $v, (array) ($row['embedding'] ?? []));
        }
        if (count($vectors) !== count($texts)) {
            throw new RuntimeException('OpenAI returned an unexpected number of embeddings.');
        }

        return $vectors;
    }

    public function ocrPage(string $imagePath, array $languages): OcrResult
    {
        $this->assertConfigured();
        $bytes = @file_get_contents($imagePath);
        if ($bytes === false) {
            throw new RuntimeException('The page image could not be read.');
        }

        $body = $this->post('/chat/completions', [
            'model' => $this->model, 'max_completion_tokens' => 2000,
            'messages' => [['role' => 'user', 'content' => [
                ['type' => 'text', 'text' => 'Transcribe all text on this page exactly. Languages: '.implode(', ', $languages).'. Return only the text.'],
                ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,'.base64_encode($bytes)]],
            ]]],
        ], 60);

        return new OcrResult((string) ($body['choices'][0]['message']['content'] ?? ''), 0.7);
    }

    public function health(): ProviderHealth
    {
        if (! $this->configured()) {
            return new ProviderHealth(false, 'OpenAI key is not configured (O-014).');
        }

        $started = hrtime(true);
        try {
            $response = $this->client(5)->get('/models/'.rawurlencode($this->model));
        } catch (Throwable) {
            return new ProviderHealth(false, 'OpenAI is unreachable.');
        }

        return new ProviderHealth($response->successful(), $response->successful() ? 'OpenAI reachable.' : 'OpenAI answered HTTP '.$response->status().'.', (int) ((hrtime(true) - $started) / 1_000_000));
    }

    private function configured(): bool
    {
        return $this->apiKey !== null && trim($this->apiKey) !== '';
    }

    private function assertConfigured(): void
    {
        if (! $this->configured()) {
            throw new AiProviderNotConfigured;
        }
    }

    /** @return \Illuminate\Http\Client\PendingRequest */
    private function client(int $timeout)
    {
        $client = Http::baseUrl(rtrim($this->baseUrl, '/'))->withToken((string) $this->apiKey)->acceptJson()->asJson()->timeout($timeout)->connectTimeout(min(5, $timeout));
        if ($this->organization !== null && $this->organization !== '') {
            $client = $client->withHeaders(['OpenAI-Organization' => $this->organization]);
        }

        return $client;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload, int $timeout): array
    {
        $this->assertConfigured();

        $response = $this->client($timeout)->post($path, $payload);
        if (! $response->successful()) {
            throw new RuntimeException('OpenAI request failed with HTTP '.$response->status().'.'); // never include the body: it can echo learner text
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /** Minor units per million tokens from `ai.openai.price_minor_per_million`; 0 until the owner supplies prices. */
    private function costMinor(int $input, int $cached, int $output): int
    {
        $price = (array) config('ai.openai.price_minor_per_million', []);
        $fresh = max(0, $input - $cached);
        $total = $fresh * (int) ($price['input'] ?? 0) + $cached * (int) ($price['cached'] ?? 0) + $output * (int) ($price['output'] ?? 0);

        return intdiv($total + 500_000, 1_000_000);
    }
}
