<?php

declare(strict_types=1);

namespace App\Filament\Resources\CourseCustomFields\Pages;

use App\Domain\Catalog\Models\CourseCustomField;
use App\Filament\Resources\CourseCustomFields\CourseCustomFieldForm;
use App\Filament\Resources\CourseCustomFields\CourseCustomFieldResource;
use App\Filament\Shared\Pages\WorkspaceListRecords;
use Filament\Actions\Action;

final class ListCourseCustomFields extends WorkspaceListRecords
{
    protected static string $resource = CourseCustomFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [CourseCustomFieldForm::configure(Action::make('create')->label(__('course_wizard.fields.create'))->icon('heroicon-o-plus'))];
    }

    /** @return array<array{label: string, value: int|string, icon?: string, tone?: string}> */
    protected function getWorkspaceSummary(): array
    {
        return [
            ['label' => __('course_wizard.fields.active'), 'value' => CourseCustomField::query()->where('is_active', true)->count(), 'icon' => 'heroicon-o-check-circle', 'tone' => 'success'],
            ['label' => __('course_wizard.fields.public'), 'value' => CourseCustomField::query()->where('visibility', CourseCustomField::VISIBILITY_PUBLIC)->count(), 'icon' => 'heroicon-o-eye'],
        ];
    }
}
