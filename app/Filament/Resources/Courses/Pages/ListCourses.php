<?php

declare(strict_types=1);

namespace App\Filament\Resources\Courses\Pages;

use App\Domain\Catalog\Queries\CourseDirectory;
use App\Domain\Shared\Markets\Market;
use App\Domain\Shared\Money\Money;
use App\Domain\Shared\Money\PriceFormatter;
use App\Filament\Resources\Courses\CourseResource;
use App\Filament\Shared\Actions\ExportCsvAction;
use App\Filament\Shared\Pages\WorkspaceListRecords;
use App\Models\Course;
use Filament\Actions\CreateAction;
use Illuminate\Support\Facades\Auth;

final class ListCourses extends WorkspaceListRecords
{
    protected static string $resource = CourseResource::class;

    protected function getHeaderActions(): array
    {
        $market = Market::Egypt;
        $columns = [
            __('courses.name') => 'title',
            __('courses.category') => static fn (Course $record): string => $record->category->name ?? __('courses.unassigned'),
            __('courses.instructors') => static fn (Course $record): string => $record->instructors->pluck('name')->implode(', ') ?: __('courses.unassigned'),
            __('courses.sections') => 'sections_count',
            __('courses.lessons') => 'lessons_count',
        ];
        if (Auth::user()?->can('learner.progress.view') ?? false) {
            $columns[__('courses.enrollments')] = 'enrollments_count';
        }
        $columns[__('courses.price')] = static function (Course $record) use ($market): string {
            $minor = $record->getAttribute('market_price');

            return $minor === null
                ? match ($record->access_mode) {
                    Course::ACCESS_FREE => __('courses.free'),
                    Course::ACCESS_SUBSCRIPTION => __('catalog.included_in_subscription'),
                    default => __('courses.unconfigured'),
                }
            : app(PriceFormatter::class)->format(Money::ofMinor((int) $minor, $market->currency()), $market, app()->getLocale());
        };
        $columns[__('courses.status')] = static fn (Course $record): string => __('courses.'.$record->status->value);
        $columns[__('courses.access')] = 'access_mode';

        return [
            CreateAction::make()->label(__('courses.new_course')),
            ExportCsvAction::make('course.view', $columns, 'courses.csv')->color('gray')->outlined(),
        ];
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
