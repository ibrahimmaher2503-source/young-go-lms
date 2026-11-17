<?php

declare(strict_types=1);

namespace App\Domain\AI\Sandbox;

use App\Domain\AI\Assistant\AssistantOutcome;
use App\Domain\AI\Assistant\GuardFailure;
use App\Domain\AI\Assistant\StyleTier;
use App\Domain\AI\Guards\GuardKind;
use App\Domain\AI\Guards\ModerationCategory;

/** What a sandbox question produced, including the guard results the learner never sees. Never stored. */
final readonly class SandboxResult
{
    /**
     * @param  list<int>  $pages
     * @param  array<string, int>  $piiKinds  masked personal data by kind (counts only)
     */
    public function __construct(
        public AssistantOutcome $outcome,
        public string $message,
        public array $pages,
        public string $language,
        public string $quote,
        public bool $quoteVerified,
        public string $maskedQuestion,
        public array $piiKinds,
        /** The input guard that stopped the question before any provider call, else null. */
        public ?GuardKind $inputGuard,
        public ?ModerationCategory $category,
        /** Why the output guard rejected the model answer (after the retry), else null. */
        public ?GuardFailure $outputFailure,
        /** provider_error or timeout when the provider call itself failed, else null. */
        public ?string $providerFailure,
        public bool $trimmed,
        public int $attempts,
        public int $latencyMs,
        public ?string $model,
        public StyleTier $tier,
    ) {}

    public function providerCalled(): bool
    {
        return $this->attempts > 0;
    }
}
