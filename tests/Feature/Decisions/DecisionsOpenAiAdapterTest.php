<?php

declare(strict_types=1);

// T-DECISIONS-2026-11-18 | O-014 | A1

use App\Domain\AI\AiProviderNotConfigured;
use App\Domain\AI\Dto\AiCompletionRequest;
use App\Domain\AI\Resilience\ProviderRegistry;
use App\Support\AI\OpenAiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function decisionsOpenAiRequest(string $prefix = 'SYSTEM RULES'): AiCompletionRequest
{
    return new AiCompletionRequest(
        promptVersion: 1,
        cacheablePrefix: $prefix,
        turns: [['role' => 'user', 'content' => 'What is a verb?']],
        maxTokens: 220,
        responseSchema: [],
        timeoutSeconds: 15,
        userHash: 'student-hash-123',
        idempotencyKey: 'decisions-openai-key',
    );
}

function decisionsOpenAiBody(): array
{
    return [
        'id' => 'chatcmpl-1',
        'model' => 'gpt-4o-mini-2026',
        'choices' => [['message' => ['content' => 'A verb is an action word.'], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 120, 'completion_tokens' => 30, 'prompt_tokens_details' => ['cached_tokens' => 40]],
    ];
}

it('resolves the openai key to an OpenAiProvider and lists it among the keys', function (): void {
    config(['ai.openai.api_key' => 'sk-test-decisions']);
    $registry = app(ProviderRegistry::class);

    expect($registry->keys())->toContain('openai')
        ->and($registry->make('openai'))->toBeInstanceOf(OpenAiProvider::class);
})->group('T-DECISIONS-2026-11-18', 'O-014', 'A1');

it('is not the active provider without a key and becomes active once a key is set', function (): void {
    config(['ai.provider' => 'openai', 'ai.openai.api_key' => null]);
    expect(app(ProviderRegistry::class)->activeKey())->not->toBe('openai');

    config(['ai.openai.api_key' => 'sk-test-decisions']);
    expect(app(ProviderRegistry::class)->activeKey())->toBe('openai');
})->group('T-DECISIONS-2026-11-18', 'O-014', 'A1');

it('refuses every call without a key and makes no HTTP request', function (): void {
    Http::preventStrayRequests();
    Http::fake();
    config(['ai.openai.api_key' => null]);
    $provider = new OpenAiProvider(null, 'gpt-4o-mini');

    expect(fn () => $provider->complete(decisionsOpenAiRequest()))->toThrow(AiProviderNotConfigured::class)
        ->and(fn () => $provider->embed(['a']))->toThrow(AiProviderNotConfigured::class)
        ->and($provider->health()->healthy)->toBeFalse();

    Http::assertNothingSent();
})->group('T-DECISIONS-2026-11-18', 'O-014', 'A1');

it('returns content, usage, request id, finish reason and model from a chat completion with the right request shape', function (): void {
    Http::preventStrayRequests();
    Http::fake(['api.openai.com/*' => Http::response(decisionsOpenAiBody())]);
    $provider = new OpenAiProvider('sk-test-decisions', 'gpt-4o-mini');

    $result = $provider->complete(decisionsOpenAiRequest());

    expect($result->content)->toBe('A verb is an action word.')
        ->and($result->providerRequestId)->toBe('chatcmpl-1')
        ->and($result->finishReason)->toBe('stop')
        ->and($result->model)->toBe('gpt-4o-mini-2026');

    Http::assertSent(function ($request): bool {
        $body = $request->data();

        return $request->hasHeader('Authorization', 'Bearer sk-test-decisions')
            && $body['user'] === 'student-hash-123'
            && $body['max_completion_tokens'] === 220
            && $body['messages'][0] === ['role' => 'system', 'content' => 'SYSTEM RULES'];
    });
})->group('T-DECISIONS-2026-11-18', 'O-014', 'A1');

it('throws on HTTP 500 with the status only and never the response body', function (): void {
    Http::preventStrayRequests();
    Http::fake(['api.openai.com/*' => Http::response('secret body text', 500)]);
    $provider = new OpenAiProvider('sk-test-decisions', 'gpt-4o-mini');

    try {
        $provider->complete(decisionsOpenAiRequest());
        $this->fail('expected a RuntimeException');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toContain('500')->not->toContain('secret body text');
    }
})->group('T-DECISIONS-2026-11-18', 'O-014', 'A1');

it('returns one vector per text and throws when the count does not match', function (): void {
    Http::preventStrayRequests();
    Http::fake(['api.openai.com/*' => Http::response(['data' => [
        ['index' => 0, 'embedding' => [0.1, 0.2]],
        ['index' => 1, 'embedding' => [0.3, 0.4]],
    ]])]);
    $provider = new OpenAiProvider('sk-test-decisions', 'gpt-4o-mini');

    expect($provider->embed(['one', 'two']))->toHaveCount(2);

    Http::swap(new Factory);
    Http::fake(['api.openai.com/*' => Http::response(['data' => [['index' => 0, 'embedding' => [0.1]]]])]);
    expect(fn () => $provider->embed(['one', 'two']))->toThrow(RuntimeException::class);
})->group('T-DECISIONS-2026-11-18', 'O-014', 'A1');

it('reads the minor-access flag from config and defaults adult age to 18', function (): void {
    expect(config('ai.openai.allow_minors'))->toBeFalse()
        ->and(config('ai.adult_age'))->toBe(18);
})->group('T-DECISIONS-2026-11-18', 'O-014', 'A1');
