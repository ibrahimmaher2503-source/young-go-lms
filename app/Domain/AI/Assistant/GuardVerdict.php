<?php

declare(strict_types=1);

namespace App\Domain\AI\Assistant;

final readonly class GuardVerdict
{
    /** @param  list<int>  $pages */
    private function __construct(
        public ?GuardFailure $failure,
        public ?AssistantOutcome $outcome,
        public string $answer,
        public array $pages,
        public string $language,
        public bool $quoteVerified,
        public bool $trimmed,
        /** The verified supporting quote. Only the console and the evaluation read it; it is never stored or shown to learners. */
        public string $quote = '',
    ) {}

    /** @param  list<int>  $pages */
    public static function accepted(AssistantOutcome $outcome, string $answer = '', array $pages = [], string $language = 'en', bool $quoteVerified = false, bool $trimmed = false, string $quote = ''): self
    {
        return new self(null, $outcome, $answer, $pages, $language, $quoteVerified, $trimmed, $quote);
    }

    public static function rejected(GuardFailure $failure): self
    {
        return new self($failure, null, '', [], 'en', false, false);
    }

    public function passed(): bool
    {
        return $this->failure === null;
    }
}
