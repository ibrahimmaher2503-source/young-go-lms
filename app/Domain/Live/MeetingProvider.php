<?php

declare(strict_types=1);

namespace App\Domain\Live;

use Illuminate\Validation\ValidationException;

/**
 * A meeting service a live session can use. Today every provider is manual: staff paste the link. A later driver can
 * create meetings through an API behind this same contract without touching the scheduling Actions.
 */
interface MeetingProvider
{
    /** @return list<string> the provider keys this driver serves */
    public function keys(): array;

    /** @throws ValidationException when the link is not usable for the provider */
    public function validateUrl(string $provider, ?string $url): ?string;
}
