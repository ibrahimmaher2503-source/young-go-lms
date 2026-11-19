<?php

declare(strict_types=1);

namespace App\Domain\Migration\Enums;

enum MigrationRecordStatus: string
{
    case Imported = 'imported';
    case Updated = 'updated';
    case Skipped = 'skipped';
    case Rejected = 'rejected';
}
