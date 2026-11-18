<?php

declare(strict_types=1);

namespace App\Domain\Privacy;

enum DataRequestType: string
{
    case Export = 'export';
    case Correction = 'correction';
    case Deletion = 'deletion';

    public function label(): string
    {
        return __('privacy.type_'.$this->value);
    }
}
