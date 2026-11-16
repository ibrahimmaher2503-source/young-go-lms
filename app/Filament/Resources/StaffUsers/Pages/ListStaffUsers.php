<?php

declare(strict_types=1);

namespace App\Filament\Resources\StaffUsers\Pages;

use App\Filament\Resources\StaffUsers\StaffUserResource;
use Filament\Resources\Pages\ListRecords;

final class ListStaffUsers extends ListRecords
{
    protected static string $resource = StaffUserResource::class;
}
