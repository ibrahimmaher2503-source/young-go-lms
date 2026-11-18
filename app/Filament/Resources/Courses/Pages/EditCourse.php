<?php

declare(strict_types=1);

namespace App\Filament\Resources\Courses\Pages;

use App\Domain\Catalog\Actions\SaveCourseWizard;
use App\Domain\Catalog\Revisions\RevisionAccess;
use App\Filament\Resources\Courses\CourseResource;
use App\Filament\Resources\Courses\Pages\Concerns\PersistsCourseWizard;
use App\Filament\Resources\Courses\Schemas\CourseWizardSteps;
use App\Models\Course;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/** @extends EditRecord<Course> */
final class EditCourse extends EditRecord
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

    /** T-P3-07: a published independent course is edited through a revision, so the wizard hands the author over to it. */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        $user = Auth::user();
        if ($this->record instanceof Course && $user instanceof User && RevisionAccess::requiresRevision($user, $this->record)) {
            $this->redirect(CourseResource::getUrl('revision', ['record' => $this->record]));
        }
    }

    protected function currentCourse(): ?Course
    {
        return $this->record instanceof Course ? $this->record : null;
    }

    /** Resume where the author left off. */
    public function getStartStep(): int
    {
        return $this->record instanceof Course ? max(1, min(SaveCourseWizard::LAST_STEP, (int) $this->record->wizard_step)) : 1;
    }

    /** @return list<Step> */
    public function getSteps(): array
    {
        return CourseWizardSteps::make(fn (int $step) => $this->persistStep($step), $this->finishActions());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->record instanceof Course ? self::courseFormData($this->record) : $data;
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
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
