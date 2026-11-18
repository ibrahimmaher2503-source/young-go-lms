<?php

declare(strict_types=1);

namespace App\Filament\Resources\InstructorApplications\Pages;

use App\Domain\Instructors\Queries\ApplicationQueue;
use App\Filament\Resources\InstructorApplications\InstructorApplicationResource;
use App\Filament\Shared\Pages\WorkspaceListRecords;

final class ListInstructorApplications extends WorkspaceListRecords
{
    protected static string $resource = InstructorApplicationResource::class;

    /** @return array<array{label: string, value: int|string, icon?: string, tone?: string}> */
    protected function getWorkspaceSummary(): array
    {
        $summary = ApplicationQueue::summary();

        return [
            ['label' => __('instructor_onboarding.summary_pending'), 'value' => $summary['pending'], 'icon' => 'heroicon-o-clock', 'tone' => 'info'],
            ['label' => __('instructor_onboarding.summary_approved'), 'value' => $summary['approved'], 'icon' => 'heroicon-o-check-circle', 'tone' => 'success'],
            ['label' => __('instructor_onboarding.summary_rejected'), 'value' => $summary['rejected'], 'icon' => 'heroicon-o-x-circle', 'tone' => 'danger'],
        ];
    }
}
