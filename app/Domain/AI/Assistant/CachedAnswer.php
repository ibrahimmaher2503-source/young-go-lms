<?php

declare(strict_types=1);

namespace App\Domain\AI\Assistant;

/** A previously verified answer taken from the cache. */
final readonly class CachedAnswer
{
    /** @param  list<int>  $pages */
    public function __construct(public string $answer, public array $pages, public string $language) {}
}
