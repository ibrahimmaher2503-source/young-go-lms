<?php

declare(strict_types=1);

namespace App\Domain\AI\Evaluation;

/** Approved lesson text for the harness: fixture pages, never a real lesson. */
final readonly class EvalSource
{
    /**
     * @param  array<int, string>  $pages  page number => approved text
     * @param  list<string>  $quizPrompts  prompts of the quiz the asker is pretending to take
     */
    public function __construct(public string $key, public string $title, public int $version, public array $pages, public array $quizPrompts) {}
}
