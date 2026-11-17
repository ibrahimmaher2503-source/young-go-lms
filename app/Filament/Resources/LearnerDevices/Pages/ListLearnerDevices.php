<?php

declare(strict_types=1);

namespace App\Filament\Resources\LearnerDevices\Pages;

use App\Filament\Resources\LearnerDevices\LearnerDeviceResource;
use App\Filament\Shared\Pages\WorkspaceListRecords;

final class ListLearnerDevices extends WorkspaceListRecords
{
    protected static string $resource = LearnerDeviceResource::class;
}
