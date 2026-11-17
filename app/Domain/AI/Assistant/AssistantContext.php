<?php

declare(strict_types=1);

namespace App\Domain\AI\Assistant;

/** The approved source for one question: what goes into the prompt, and every page of that version for verification. */
final readonly class AssistantContext
{
    public const string FULL = 'full';

    public const string CHUNKED = 'chunked';

    /**
     * @param  array<int, string>  $promptPages  page number => approved text sent to the provider
     * @param  array<int, string>  $allPages  every approved page of the version, used to verify citations and quotes
     */
    public function __construct(public string $mode, public int $documentVersion, public array $promptPages, public array $allPages) {}
}
