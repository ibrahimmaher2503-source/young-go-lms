<?php

declare(strict_types=1);

namespace App\Filament\Resources\Courses\Pages;

use App\Domain\Catalog\Actions\SaveCourseWizard;
use App\Filament\Resources\Courses\CourseResource;
use App\Filament\Resources\Courses\Pages\Concerns\PersistsCourseWizard;
use App\Filament\Resources\Courses\Schemas\CourseWizardSteps;
use App\Models\Course;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/** @extends CreateRecord<Course> */
final class CreateCourse extends CreateRecord
{
    use HasWizard;
    use PersistsCourseWizard;

    protected static string $resource = CourseResource::class;

    /** Each step is validated and saved when passed; "Save draft" keeps incomplete work. */
    protected function hasSkippableSteps(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $this->courseWizardForm($schema);
    }

    protected function currentCourse(): ?Course
    {
        return $this->record instanceof Course ? $this->record : null;
    }

    /** @return list<Step> */
    public function getSteps(): array
    {
        return CourseWizardSteps::make(fn (int $step) => $this->persistStep($step));
    }

    protected function handleRecordCreation(array $data): Model
    {
        return $this->persistStep(SaveCourseWizard::LAST_STEP);
    }

    protected function getHeaderActions(): array
    {
        return [$this->saveDraftAction()];
    }

    protected function getRedirectUrl(): string
    {
        return CourseResource::getUrl('index');
    }
}
