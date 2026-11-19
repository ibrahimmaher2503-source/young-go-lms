<?php

declare(strict_types=1);

namespace App\Filament\Resources\Courses\Pages\Concerns;

use App\Domain\Catalog\Actions\PublishReadyCourse;
use App\Domain\Catalog\Actions\SaveCourseWizard;
use App\Domain\Catalog\Actions\SubmitCourseForReview;
use App\Domain\Catalog\CourseStatus;
use App\Domain\Commerce\CoursePrice;
use App\Filament\Resources\Courses\CourseResource;
use App\Models\Course;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
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
        // The stored cover replaces the temporary upload so the next step does not store it again.
        $this->data['cover_image'] = $course->cover_image === null ? [] : [(string) Str::uuid() => $course->cover_image];
        if ($step >= SaveCourseWizard::MEDIA_STEP && array_key_exists('thumbnail_path', $this->data ?? [])) {
            $this->data['thumbnail_path'] = $course->thumbnail_path === null ? [] : [$course->thumbnail_path];
        }

        return $course;
    }

    /** @return list<Action> Finish-step buttons (T-P3-03). Each runs a Courses domain action and reports its refusal inline. */
    protected function finishActions(): array
    {
        $course = fn (): ?Course => $this->currentCourse();

        return [
            Action::make('submitForReview')->label(__('course_wizard.submit_review'))->icon('heroicon-o-paper-airplane')->color('primary')
                ->requiresConfirmation()->modalDescription(__('course_wizard.submit_review_confirm'))
                ->visible(static fn (): bool => $course() !== null && $course()->status === CourseStatus::Draft && Gate::allows('update', $course()))
                ->action(fn () => $this->finishWith(static fn (User $actor, Course $record) => app(SubmitCourseForReview::class)($actor, $record), 'submitted')),
            Action::make('publishNow')->label(__('course_wizard.publish'))->icon('heroicon-o-globe-alt')->color('success')
                ->requiresConfirmation()->modalDescription(__('course_wizard.publish_confirm'))
                ->visible(static fn (): bool => $course() !== null && in_array($course()->status, [CourseStatus::Draft, CourseStatus::PendingReview, CourseStatus::Private, CourseStatus::Upcoming], true) && Gate::allows('publish', $course()))
                ->action(fn () => $this->finishWith(static fn (User $actor, Course $record) => app(PublishReadyCourse::class)($actor, $record), 'published')),
        ];
    }

    /** @param Closure(User, Course): mixed $run */
    private function finishWith(Closure $run, string $done): void
    {
        $user = Auth::user();
        $record = $this->currentCourse();
        abort_unless($user instanceof User && $record !== null, 403);

        try {
            $this->record = $run($user, $record) instanceof Course ? Course::query()->findOrFail($record->getKey()) : $record;
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();

            return;
        }

        Notification::make()->success()->title(__('course_wizard.'.$done))->send();
        $this->redirect(CourseResource::getUrl('index'));
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
            unset($data['access_mode'], $data['access_duration_days'], $data['bundle_eligible'], $data['manual_access_allowed']);
        }
        // T-P3-03: custom values, promo video (provider and the link as entered) and the stored cover path.
        $data['custom'] = $course->custom_values ?? [];
        $data['promo_video'] = ['provider' => $course->promo_video['provider'] ?? null, 'url' => $course->promo_video['source_url'] ?? null];
        $data['cover_image'] = $course->cover_image === null ? [] : [(string) Str::uuid() => $course->cover_image];
        $data['seo_title'] = $course->getTranslations('seo_title');
        $data['seo_description'] = $course->getTranslations('seo_description');
        $data['seo_keywords'] = $course->getTranslations('seo_keywords');
        unset($data['custom_values']);

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
