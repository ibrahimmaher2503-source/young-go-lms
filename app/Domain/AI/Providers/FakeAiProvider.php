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
 * With $grounded on (local demo only, see ProviderRegistry::make), an empty queue answers from the lesson pages in the prompt:
 * the sentence sharing the most words with the question, quoted and cited, or the usual refusal when nothing matches.
 */
final class FakeAiProvider implements AiProviderInterface
{
    public const string DEFAULT_ANSWER = '{"status":"not_in_source","answer":"","source_pages":[],"support_quote":"","language":"en"}';

    /** @var list<string|array<string, mixed>|Closure(AiCompletionRequest): string> */
    private array $queue = [];

    /** @var list<Throwable> */
    private array $failures = [];

    /** @var list<AiCompletionRequest> */
    private array $requests = [];

    private bool $healthy = true;

    public function __construct(private readonly bool $grounded = false) {}

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

        $next = $this->queue !== [] ? array_shift($this->queue) : ($this->grounded ? $this->groundedAnswer($request) : self::DEFAULT_ANSWER);
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
        return new OcrResult('FAKE OCR TEXT read from the scanned page', 0.9);
    }

    public function health(): ProviderHealth
    {
        return new ProviderHealth($this->healthy, $this->healthy ? 'fake provider' : 'fake provider set to unhealthy', 1);
    }

    /** Deterministic answer built from the lesson text in the prompt; never invents content beyond one quoted sentence. */
    private function groundedAnswer(AiCompletionRequest $request): string
    {
        $question = '';
        foreach (array_reverse($request->turns) as $turn) {
            if ($turn['role'] === 'user') {
                $question = $turn['content'];
                break;
            }
        }
        $words = static fn (string $text): array => array_values(array_filter(
            preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [],
            static fn (string $word): bool => mb_strlen($word) >= 4,
        ));
        $asked = array_flip($words($question));
        $tag = (string) config('ai.source_tag', 'lesson_source');
        if ($asked === [] || preg_match_all('/\[\[page (\d+)\]\]\n(.*?)(?=\[\[page \d+\]\]|<\/'.preg_quote($tag, '/').'|\z)/su', $request->cacheablePrefix, $found, PREG_SET_ORDER) === 0) {
            return self::DEFAULT_ANSWER;
        }

        [$bestPage, $bestSentence, $bestScore] = [0, '', 0];
        foreach ($found as [, $page, $text]) {
            foreach (preg_split('/(?<=[.!?؟])\s+|\n+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $sentence) {
                $score = count(array_intersect_key(array_flip($words($sentence)), $asked));
                if ($score > $bestScore) {
                    [$bestPage, $bestSentence, $bestScore] = [(int) $page, trim($sentence), $score];
                }
            }
        }
        if ($bestScore === 0) {
            return self::DEFAULT_ANSWER;
        }

        $quote = implode(' ', array_slice(preg_split('/\s+/u', $bestSentence, -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 20));
        $arabic = preg_match('/\p{Arabic}/u', $question) === 1;
        $answer = ($arabic ? 'بحسب مادة الدرس: ' : 'According to the lesson: ').rtrim($quote, '.!?؟').'.';

        return (string) json_encode([
            'status' => 'answered', 'answer' => $answer, 'source_pages' => [$bestPage],
            'support_quote' => $quote, 'language' => $arabic ? 'ar' : 'en',
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
