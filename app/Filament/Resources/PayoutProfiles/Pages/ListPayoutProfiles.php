<?php

declare(strict_types=1);

namespace App\Filament\Resources\PayoutProfiles\Pages;

use App\Filament\Resources\PayoutProfiles\PayoutProfileResource;
use App\Filament\Shared\Pages\WorkspaceListRecords;

final class ListPayoutProfiles extends WorkspaceListRecords
{
    protected static string $resource = PayoutProfileResource::class;
}
