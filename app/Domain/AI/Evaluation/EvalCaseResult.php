<?php

declare(strict_types=1);

namespace App\Domain\AI\Evaluation;

/** The outcome of one evaluation case. Failures are short machine-readable reasons, never model text. */
final readonly class EvalCaseResult
{
    /**
     * @param  list<string>  $failures
     * @param  list<int>  $pages
     * @param  bool|null  $lengthOk  null when the case produced no answer
     */
    public function __construct(
        public string $id,
        public string $lang,
        public EvalCategory $category,
        public array $failures,
        public string $outcome,
        public ?string $guard,
        public array $pages,
        public int $attempts,
        public int $latencyMs,
        public ?bool $lengthOk,
    ) {}

    public function passed(): bool
    {
        return $this->failures === [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id, 'lang' => $this->lang, 'category' => $this->category->value, 'metric' => $this->category->metric(),
            'passed' => $this->passed(), 'failures' => $this->failures, 'outcome' => $this->outcome, 'guard' => $this->guard,
            'pages' => $this->pages, 'attempts' => $this->attempts, 'latency_ms' => $this->latencyMs, 'length_ok' => $this->lengthOk,
        ];
    }
}
