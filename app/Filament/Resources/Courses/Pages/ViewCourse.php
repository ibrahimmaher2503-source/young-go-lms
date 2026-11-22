<?php

declare(strict_types=1);

namespace App\Filament\Resources\Courses\Pages;

use App\Filament\Resources\Courses\CourseResource;
use App\Models\Course;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Gate;

/** @extends ViewRecord<Course> */
final class ViewCourse extends ViewRecord
{
    protected static string $resource = CourseResource::class;

    protected array $extraBodyAttributes = ['class' => 'yg-course-detail'];

    public function getTitle(): string
    {
        return $this->getRecord()->title;
    }

    public function getBreadcrumbs(): array
    {
        return [...$this->getResourceBreadcrumbs(), __('courses.details')];
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->icon('heroicon-o-pencil-square'),
            Action::make('curriculum')->label(__('curriculum.curriculum'))->icon('heroicon-o-queue-list')
                ->color('gray')->outlined()
                ->url(fn (): string => CourseResource::getUrl('curriculum', ['record' => $this->getRecord()]))
                ->authorize(fn (): bool => Gate::allows('update', $this->getRecord())),
        ];
    }
}
