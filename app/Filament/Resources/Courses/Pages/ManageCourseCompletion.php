<?php

declare(strict_types=1);

namespace App\Filament\Resources\Courses\Pages;

use App\Domain\Certificates\Actions\ReissueCertificate;
use App\Domain\Certificates\Actions\RevokeCertificate;
use App\Domain\Certificates\Actions\SaveCertificateTemplates;
use App\Domain\Certificates\Certificate;
use App\Domain\Certificates\CertificateTemplate;
use App\Domain\Completion\Actions\ApproveCourseCompletion;
use App\Domain\Completion\Actions\SaveCompletionRules;
use App\Domain\Completion\CompletionReport;
use App\Domain\Completion\CourseCompletion;
use App\Domain\Completion\CourseCompletionRule;
use App\Domain\Entitlements\CourseLearners;
use App\Filament\Resources\Courses\CourseResource;
use App\Models\Course;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\WithPagination;

/** T-P6-05: completion rules of a course and a per-learner status with a manual approval. Same gate as editing the course. */
final class ManageCourseCompletion extends Page
{
    use InteractsWithRecord;
    use WithPagination;

    protected static string $resource = CourseResource::class;

    protected string $view = 'filament.courses.completion';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $user = Auth::user();
        abort_unless($user instanceof User && Gate::forUser($user)->allows('update', $this->record), 403);
    }

    public function getTitle(): string
    {
        return __('completion.title');
    }

    protected function getHeaderActions(): array
    {
        return [$this->editRulesAction(), $this->editCertificateTemplatesAction()];
    }

    public function editCertificateTemplatesAction(): Action
    {
        return Action::make('editCertificateTemplates')->label(__('certificates.edit_templates'))
            ->visible(fn (): bool => $this->actor()->can('system.settings.manage'))
            ->schema([
                Textarea::make('ar')->label(__('certificates.issuer_text_ar'))->maxLength(500)->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar']),
                Textarea::make('en')->label(__('certificates.issuer_text_en'))->maxLength(500)->extraInputAttributes(['dir' => 'ltr', 'lang' => 'en']),
            ])
            ->fillForm(fn (): array => CertificateTemplate::query()->pluck('issuer_text', 'locale')->all())
            ->action(function (array $data): void {
                app(SaveCertificateTemplates::class)($this->actor(), $data);
                Notification::make()->success()->title(__('completion.saved'))->send();
            });
    }

    public function editRulesAction(): Action
    {
        return Action::make('editRules')->label(__('completion.edit_rules'))->icon('heroicon-o-adjustments-horizontal')
            ->schema([
                TextInput::make('lesson_percent')->label(__('completion.lesson_percent'))->helperText(__('completion.lesson_percent_help'))->numeric()->minValue(1)->maxValue(100),
                Toggle::make('require_quizzes')->label(__('completion.require_quizzes')),
                Toggle::make('require_assignments')->label(__('completion.require_assignments'))->helperText(__('completion.assignments_help')),
                TextInput::make('attendance_percent')->label(__('completion.attendance_percent'))->helperText(__('completion.attendance_help'))->numeric()->minValue(1)->maxValue(100),
                Toggle::make('manual_approval')->label(__('completion.manual_approval'))->helperText(__('completion.manual_approval_help')),
            ])
            ->fillForm(fn (): array => CourseCompletionRule::forCourse($this->course())->values())
            ->action(function (array $data, Action $action): void {
                try {
                    app(SaveCompletionRules::class)($this->actor(), $this->course(), $data);
                } catch (ValidationException $exception) {
                    $this->refuse($exception);
                    $action->halt();

                    return;
                }
                Notification::make()->success()->title(__('completion.saved'))->send();
            });
    }

    public function approveAction(): Action
    {
        return Action::make('approve')->label(__('completion.approve'))->modalHeading(__('completion.approve'))->modalDescription(__('completion.approve_confirm'))
            ->schema([Textarea::make('reason')->label(__('completion.reason'))->required()->minLength(3)->maxLength(500)])
            ->action(function (array $data, array $arguments, Action $action): void {
                $learner = User::query()->find((int) ($arguments['learner'] ?? 0));
                try {
                    abort_unless($learner instanceof User, 404);
                    app(ApproveCourseCompletion::class)($this->actor(), $this->course(), $learner, (string) $data['reason']);
                } catch (ValidationException $exception) {
                    $this->refuse($exception);
                    $action->halt();

                    return;
                }
                Notification::make()->success()->title(__('completion.approved'))->send();
            });
    }

    public function revokeCertificateAction(): Action
    {
        return Action::make('revokeCertificate')->label(__('certificates.staff.revoke'))->color('danger')->modalHeading(__('certificates.staff.revoke'))->modalDescription(__('certificates.staff.revoke_confirm'))
            ->schema([Textarea::make('reason')->label(__('certificates.staff.reason'))->required()->minLength(3)->maxLength(500)])
            ->action(function (array $data, array $arguments, Action $action): void {
                try {
                    $certificate = Certificate::query()->where('course_id', $this->course()->getKey())->find((int) ($arguments['certificate'] ?? 0));
                    abort_unless($certificate instanceof Certificate, 404);
                    app(RevokeCertificate::class)($this->actor(), $certificate, (string) $data['reason']);
                } catch (ValidationException $exception) {
                    $this->refuse($exception);
                    $action->halt();

                    return;
                }
                Notification::make()->success()->title(__('certificates.staff.revoked_done'))->send();
            });
    }

    public function reissueCertificateAction(): Action
    {
        return Action::make('reissueCertificate')->label(__('certificates.staff.reissue'))->modalHeading(__('certificates.staff.reissue'))->modalDescription(__('certificates.staff.reissue_confirm'))
            ->schema([Textarea::make('reason')->label(__('certificates.staff.reason'))->required()->minLength(3)->maxLength(500)])
            ->action(function (array $data, array $arguments, Action $action): void {
                try {
                    $completion = CourseCompletion::query()->where('course_id', $this->course()->getKey())->find((int) ($arguments['completion'] ?? 0));
                    abort_unless($completion instanceof CourseCompletion, 404);
                    app(ReissueCertificate::class)($this->actor(), $completion, (string) $data['reason']);
                } catch (ValidationException $exception) {
                    $this->refuse($exception);
                    $action->halt();

                    return;
                }
                Notification::make()->success()->title(__('certificates.staff.reissued_done'))->send();
            });
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $course = $this->course();
        $rules = CourseCompletionRule::forCourse($course);
        /** @var LengthAwarePaginator<int, User> $learners */
        $learners = CourseLearners::query($course)->orderBy('users.name')->paginate(15);
        $done = CourseCompletion::query()->where('course_id', $course->getKey())->whereIn('learner_id', $learners->pluck('id'))->get()->keyBy('learner_id');
        $certificates = Certificate::query()->where('course_id', $course->getKey())->whereIn('learner_id', $learners->pluck('id'))->orderByDesc('id')->get()->groupBy('learner_id')->map(fn ($rows) => $rows->first());
        $reports = [];
        foreach ($learners as $learner) {
            if (! $done->has($learner->getKey())) {
                $reports[$learner->getKey()] = CompletionReport::measure($learner, $course, $rules);
            }
        }

        return ['course' => $course, 'rules' => $rules, 'learners' => $learners, 'done' => $done, 'reports' => $reports, 'certificates' => $certificates];
    }

    private function refuse(ValidationException $exception): void
    {
        Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();
    }

    private function course(): Course
    {
        /** @var Course $course */
        $course = $this->record;

        return $course;
    }

    private function actor(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
