<?php

declare(strict_types=1);

namespace App\Filament\Resources\InstructorAgreements\Pages;

use App\Filament\Resources\InstructorAgreements\InstructorAgreementResource;
use App\Filament\Shared\Pages\WorkspaceListRecords;

final class ListInstructorAgreements extends WorkspaceListRecords
{
    protected static string $resource = InstructorAgreementResource::class;

    protected function getHeaderActions(): array
    {
        return [InstructorAgreementResource::newAction()];
    }
}
