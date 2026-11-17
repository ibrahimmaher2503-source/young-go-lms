<?php

declare(strict_types=1);

namespace App\Filament\Resources\Courses\Pages\Concerns;

use App\Domain\Cohorts\Cohort;
use App\Domain\Live\Actions\CancelLiveSession;
use App\Domain\Live\Actions\CompleteLiveSession;
use App\Domain\Live\Actions\OpenLiveSession;
use App\Domain\Live\Actions\RescheduleLiveSession;
use App\Domain\Live\Actions\ScheduleLiveSession;
use App\Models\CourseSection;
use App\Models\LiveSession;
use App\Models\User;
use DateTimeZone;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

/** Live session scheduling inside the curriculum manager (T-P5-02). Uses the page's course(), actor() and attempt() helpers. */
trait ManagesLiveSessions
{
    public function scheduleLiveSessionAction(): Action
    {
        return Action::make('scheduleLiveSession')->label(__('live.schedule'))->icon('heroicon-o-video-camera')->color('gray')
            ->visible(fn (): bool => $this->actor()->can('live_session.manage'))
            ->schema([
                Select::make('section_id')->label(__('curriculum.section'))->required()->native(false)
                    ->options(fn (): array => CourseSection::query()->where('course_id', $this->course()->getKey())->whereNull('archived_at')->orderBy('sort')->get()->mapWithKeys(fn (CourseSection $s): array => [$s->getKey() => (string) $s->title])->all()),
                TextInput::make('title.ar')->label(__('curriculum.lesson_title').' (AR)')->maxLength(150)->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar']),
                TextInput::make('title.en')->label(__('curriculum.lesson_title').' (EN)')->maxLength(150)->extraInputAttributes(['dir' => 'ltr', 'lang' => 'en']),
                ...$this->liveTimeFields(),
                Select::make('provider')->label(__('live.provider'))->options($this->meetingProviderOptions())->default('manual_zoom')->required()->native(false),
                TextInput::make('meeting_url')->label(__('live.meeting_url'))->helperText(__('live.meeting_url_help'))->url()->maxLength(2048)->extraInputAttributes(['dir' => 'ltr']),
                TextInput::make('join_window_minutes')->label(__('live.join_window'))->numeric()->minValue(0)->maxValue(240)->default(15),
                Select::make('cohort_id')->label(__('live.cohort'))->native(false)->placeholder(__('live.whole_course'))
                    ->options(fn (): array => Cohort::query()->where('course_id', $this->course()->getKey())->orderBy('id')->pluck('name', 'id')->all()),
                Select::make('instructor_id')->label(__('live.instructor'))->native(false)
                    ->options(fn (): array => $this->course()->instructors()->orderBy('name')->pluck('users.name', 'users.id')->all()),
            ])
            ->action(function (array $data, Action $action): void {
                $section = CourseSection::query()->where('course_id', $this->course()->getKey())->whereNull('archived_at')->findOrFail($data['section_id']);
                if (! $this->attempt($action, fn () => app(ScheduleLiveSession::class)($this->actor(), $section, $this->liveData($data)))) {
                    return;
                }
                Notification::make()->title(__('live.saved'))->success()->send();
            });
    }

    public function rescheduleLiveSessionAction(): Action
    {
        return Action::make('rescheduleLiveSession')->label(__('live.reschedule'))->modalHeading(__('live.reschedule'))
            ->schema([...$this->liveTimeFields(), TextInput::make('meeting_url')->label(__('live.meeting_url'))->url()->maxLength(2048)->extraInputAttributes(['dir' => 'ltr'])])
            ->fillForm(function (array $arguments): array {
                $session = $this->liveSessionFor($arguments['session'] ?? 0);
                $zone = $session->timezone;

                return [
                    'starts_at' => $session->starts_at->copy()->setTimezone($zone)->format('Y-m-d H:i:s'),
                    'ends_at' => $session->ends_at->copy()->setTimezone($zone)->format('Y-m-d H:i:s'),
                    'timezone' => $zone, 'meeting_url' => $session->meeting_url,
                ];
            })
            ->action(function (array $data, array $arguments, Action $action): void {
                $session = $this->liveSessionFor($arguments['session'] ?? 0);
                if (! $this->attempt($action, fn () => app(RescheduleLiveSession::class)($this->actor(), $session, $this->liveData($data)))) {
                    return;
                }
                Notification::make()->title(__('live.saved'))->success()->send();
            });
    }

    public function cancelLiveSessionAction(): Action
    {
        return Action::make('cancelLiveSession')->label(__('live.cancel'))->color('danger')->requiresConfirmation()->modalDescription(__('live.cancel_confirm'))
            ->action(function (array $arguments, Action $action): void {
                $session = $this->liveSessionFor($arguments['session'] ?? 0);
                $this->attempt($action, fn () => app(CancelLiveSession::class)($this->actor(), $session));
            });
    }

    /** Open and finish are one click each; a refused move (no link yet, wrong state) is shown as a message. */
    public function advanceLiveSession(int $sessionId, string $step): void
    {
        $session = $this->liveSessionFor($sessionId);
        $action = $step === 'open' ? OpenLiveSession::class : CompleteLiveSession::class;
        try {
            app($action)($this->actor(), $session);
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();
        }
    }

    /** @return list<mixed> */
    private function liveTimeFields(): array
    {
        return [
            Select::make('timezone')->label(__('live.timezone'))->helperText(__('live.timezone_help'))->options(array_combine(DateTimeZone::listIdentifiers(), DateTimeZone::listIdentifiers()))
                ->searchable()->native(false)->required()->default(fn (): string => (string) ($this->actor()->timezone ?: 'UTC')),
            DateTimePicker::make('starts_at')->label(__('live.starts_at'))->seconds(false)->native(false)->required(),
            DateTimePicker::make('ends_at')->label(__('live.ends_at'))->seconds(false)->native(false)->required(),
        ];
    }

    /** @return array<string, string> */
    private function meetingProviderOptions(): array
    {
        return collect(LiveSession::PROVIDERS)->mapWithKeys(fn (string $key): array => [$key => __('live.providers.'.$key)])->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function liveData(array $data): array
    {
        return [...$data, 'cohort_id' => filled($data['cohort_id'] ?? null) ? (int) $data['cohort_id'] : null, 'instructor_id' => filled($data['instructor_id'] ?? null) ? (int) $data['instructor_id'] : null];
    }

    private function liveSessionFor(int|string $id): LiveSession
    {
        return LiveSession::query()->where('course_id', $this->course()->getKey())->with('course')->findOrFail($id);
    }

    private function viewer(): User
    {
        return $this->actor();
    }
}
