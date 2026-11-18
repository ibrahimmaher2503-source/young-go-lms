<?php

declare(strict_types=1);

namespace App\Filament\Resources\Courses\Pages\Concerns;

use App\Domain\Catalog\Actions\SaveCourseWizard;
use App\Domain\Commerce\CoursePrice;
use App\Models\Course;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/** Shared save/resume behaviour for the create and edit wizard pages. */
trait PersistsCourseWizard
{
    /** Saves what is entered so far; $complete is true when the user passes a step. */
    protected function persistStep(int $step, bool $complete = true): Course
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        try {
            $course = app(SaveCourseWizard::class)($user, $this->form->getRawState(), $this->currentCourse(), $step, $complete);
        } catch (ValidationException $exception) {
            // The form state lives under "data", so field errors must carry that prefix.
            throw ValidationException::withMessages(array_combine(
                array_map(static fn (string $key): string => 'data.'.$key, array_keys($exception->errors())),
                array_values($exception->errors()),
            ));
        }

        $this->record = $course;

        return $course;
    }

    abstract protected function currentCourse(): ?Course;

    /** @return array<string, mixed> */
    protected static function courseFormData(Course $course): array
    {
        $data = [
            ...$course->attributesToArray(),
            'title' => $course->getTranslations('title'),
            'short_description' => $course->getTranslations('short_description'),
            'description' => $course->getTranslations('description'),
            'status' => $course->status->value,
            'faqs' => $course->faqs ?? [],
            'requirements' => $course->requirements ?? [],
            'outcomes' => $course->outcomes ?? [],
        ];
        $user = Auth::user();
        if ($user instanceof User && SaveCourseWizard::canSetPricing($user)) {
            $data['market_prices'] = CoursePrice::query()->where('course_id', $course->getKey())->get()
                ->mapWithKeys(static fn (CoursePrice $price): array => [$price->market => (string) $price->money()->value->getAmount()])
                ->all();
        } else {
            unset($data['access_mode']);
        }

        return $data;
    }

    protected function courseWizardForm(Schema $schema): Schema
    {
        return parent::form($schema)->columns(null)->components([
            $this->getWizardComponent()->extraAttributes(['class' => 'yg-course-wizard']),
        ]);
    }

    protected function saveDraftAction(): Action
    {
        return Action::make('saveDraft')->label(__('courses.save_draft'))->color('primary')->icon('heroicon-o-bookmark')
            ->action(function (): void {
                $this->persistStep((int) ($this->currentCourse()->wizard_step ?? 1), false);
                Notification::make()->title(__('courses.draft_saved'))->success()->send();
            });
    }
}
