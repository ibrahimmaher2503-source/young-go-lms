<?php

declare(strict_types=1);

namespace App\Domain\AI\Evaluation;

/** The machine-readable result of one evaluation run (written as JSON by `ai:evaluate`). */
final readonly class EvalReport
{
    /** @param  array<string, mixed>  $data */
    public function __construct(public array $data) {}

    public function passed(): bool
    {
        return ($this->data['result'] ?? 'fail') === 'pass';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }

    public function toJson(): string
    {
        return (string) json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
