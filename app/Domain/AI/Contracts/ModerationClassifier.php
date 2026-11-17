<?php

declare(strict_types=1);

namespace App\Domain\AI\Contracts;

use App\Domain\AI\Guards\ModerationCategory;

/**
 * Moderation adapter (AI spec 8.1). It only ever sees text that PII masking already cleaned. A real provider adapter
 * comes with O-014; until then the keyword baseline runs, and tests use the fake.
 */
interface ModerationClassifier
{
    /** The first category the text falls into, or null when it is clean. */
    public function classify(string $maskedText): ?ModerationCategory;
}
