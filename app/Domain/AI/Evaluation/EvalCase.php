<?php

declare(strict_types=1);

namespace App\Domain\AI\Evaluation;

use App\Domain\AI\Assistant\StyleTier;

/** One evaluation question with what a correct assistant must do. `responses` is what the scripted fake provider returns per attempt. */
final readonly class EvalCase
{
    /**
     * @param  list<string|array<string, mixed>>  $responses
     * @param  array<string, mixed>  $expect
     */
    public function __construct(
        public string $id,
        public string $lang,
        public EvalCategory $category,
        public string $source,
        public string $question,
        public string $locale,
        public StyleTier $tier,
        public array $responses,
        public array $expect,
    ) {}
}
