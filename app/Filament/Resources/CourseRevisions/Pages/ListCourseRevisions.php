<?php

declare(strict_types=1);

namespace App\Filament\Resources\CourseRevisions\Pages;

use App\Domain\Catalog\Revisions\Queries\CourseRevisions;
use App\Filament\Resources\CourseRevisions\CourseRevisionResource;
use App\Filament\Shared\Pages\WorkspaceListRecords;
use Illuminate\Support\Facades\Auth;

final class ListCourseRevisions extends WorkspaceListRecords
{
    protected static string $resource = CourseRevisionResource::class;

    /** @return array<array{label: string, value: int|string, icon?: string, tone?: string}> */
    protected function getWorkspaceSummary(): array
    {
        return [
            ['label' => __('course_revisions.summary_pending'), 'value' => CourseRevisions::pendingCount(Auth::user()), 'icon' => 'heroicon-o-clock', 'tone' => 'warning'],
        ];
    }
}
