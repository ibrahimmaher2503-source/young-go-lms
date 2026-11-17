<?php

declare(strict_types=1);

namespace App\Filament\Resources\Courses\Pages;

use App\Domain\Catalog\Queries\CourseDirectory;
use App\Filament\Resources\Courses\CourseResource;
use App\Filament\Shared\Actions\ExportCsvAction;
use App\Filament\Shared\Pages\WorkspaceListRecords;
use Filament\Actions\CreateAction;
use Illuminate\Support\Facades\Auth;

final class ListCourses extends WorkspaceListRecords
{
    protected static string $resource = CourseResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label(__('courses.new_course')), ExportCsvAction::make('course.view', [
            __('courses.name') => 'title',
            __('courses.status') => 'status',
            __('courses.access') => 'access_mode',
        ], 'courses.csv')];
    }

    protected function getWorkspaceSummary(): array
    {
        $counts = CourseDirectory::counts(Auth::user());

        return [
            ['label' => __('courses.active'), 'value' => $counts['active'], 'icon' => 'heroicon-o-check-circle', 'tone' => 'success'],
            ['label' => __('courses.paid'), 'value' => $counts['paid'], 'icon' => 'heroicon-o-banknotes'],
            ['label' => __('courses.free'), 'value' => $counts['free'], 'icon' => 'heroicon-o-gift'],
            ['label' => __('courses.upcoming'), 'value' => $counts['upcoming'], 'icon' => 'heroicon-o-clock', 'tone' => 'info'],
            ['label' => __('courses.suspended'), 'value' => $counts['suspended'], 'icon' => 'heroicon-o-pause-circle'],
        ];
    }
}
