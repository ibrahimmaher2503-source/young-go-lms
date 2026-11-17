<?php

declare(strict_types=1);

namespace App\Domain\AI\Help;

use App\Models\HelpRequest;

/** What recording feedback did: nothing more for "understood", a new or an already existing request for "not understood". */
final readonly class FeedbackOutcome
{
    public function __construct(public string $type, public ?HelpRequest $request = null, public bool $created = false) {}
}
