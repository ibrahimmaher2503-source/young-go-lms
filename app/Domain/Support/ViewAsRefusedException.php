<?php

declare(strict_types=1);

namespace App\Domain\Support;

use RuntimeException;

final class ViewAsRefusedException extends RuntimeException
{
    public function __construct(public readonly ViewAsRefusal $refusal)
    {
        parent::__construct($refusal->label());
    }
}
