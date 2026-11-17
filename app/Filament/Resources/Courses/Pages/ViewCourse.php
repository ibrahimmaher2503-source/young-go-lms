<?php

declare(strict_types=1);

namespace App\Filament\Resources\Courses\Pages;

use App\Filament\Resources\Courses\CourseResource;
use App\Models\Course;
use Filament\Resources\Pages\ViewRecord;

/** @extends ViewRecord<Course> */
final class ViewCourse extends ViewRecord
{
    protected static string $resource = CourseResource::class;
}
