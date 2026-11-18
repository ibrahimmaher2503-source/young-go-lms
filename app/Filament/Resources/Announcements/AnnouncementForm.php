<?php

declare(strict_types=1);

namespace App\Filament\Resources\Announcements;

use App\Domain\Cohorts\Cohort;
use App\Domain\Notifications\Actions\SaveAnnouncement;
use App\Domain\Notifications\Announcement;
use App\Models\Course;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/** Create/edit modal for an announcement draft (header "create" without a record, row "edit" with one). Saves through SaveAnnouncement. */
final class AnnouncementForm
{
    public static function configure(Action $action): Action
    {
        return $action->schema([
            Select::make('course_id')->label(__('notification_events.announcement_course'))->required()->searchable()->live()
                ->options(static function (): array {
                    $user = Auth::user();
                    $query = Course::query()->orderByDesc('id')->limit(200);
                    if ($user instanceof User && ! $user->can('panel.admin.access')) {
                        $query->whereHas('instructors', static fn (Builder $q) => $q->whereKey($user->getKey()));
                    }

                    return $query->get()->mapWithKeys(static fn (Course $c): array => [$c->getKey() => (string) $c->title])->all();
                })
                ->afterStateUpdated(static fn ($set) => $set('cohort_id', null)),
            Select::make('cohort_id')->label(__('notification_events.announcement_cohort'))->helperText(__('notification_events.announcement_all_enrolled'))
                ->options(static fn ($get): array => filled($get('course_id'))
                    ? Cohort::query()->where('course_id', $get('course_id'))->orderBy('name')->get()->mapWithKeys(static fn (Cohort $c): array => [$c->getKey() => $c->localizedName()])->all()
                    : []),
            TextInput::make('title_ar')->label(__('notification_events.field_title_ar'))->required()->maxLength(SaveAnnouncement::TITLE_MAX)->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar']),
            Textarea::make('body_ar')->label(__('notification_events.field_body_ar'))->required()->rows(4)->maxLength(SaveAnnouncement::BODY_MAX)->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar']),
            TextInput::make('title_en')->label(__('notification_events.field_title_en'))->required()->maxLength(SaveAnnouncement::TITLE_MAX)->extraInputAttributes(['dir' => 'ltr', 'lang' => 'en']),
            Textarea::make('body_en')->label(__('notification_events.field_body_en'))->required()->rows(4)->maxLength(SaveAnnouncement::BODY_MAX)->extraInputAttributes(['dir' => 'ltr', 'lang' => 'en']),
        ])->fillForm(static fn (?Announcement $record): array => $record === null ? [] : $record->only(['course_id', 'cohort_id', 'title_ar', 'title_en', 'body_ar', 'body_en']))
            ->action(static function (array $data, Action $action, ?Announcement $record): void {
                $actor = Auth::user();
                abort_unless($actor instanceof User, 401);
                try {
                    app(SaveAnnouncement::class)($actor, [
                        'course_id' => (int) $data['course_id'], 'cohort_id' => filled($data['cohort_id'] ?? null) ? (int) $data['cohort_id'] : null,
                        'title_ar' => (string) $data['title_ar'], 'title_en' => (string) $data['title_en'],
                        'body_ar' => (string) $data['body_ar'], 'body_en' => (string) $data['body_en'],
                    ], $record);
                } catch (ValidationException $exception) {
                    Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();
                    $action->halt();

                    return;
                } catch (AuthorizationException) {
                    Notification::make()->danger()->title(__('notification_events.announcement_forbidden'))->send();
                    $action->halt();

                    return;
                }
                Notification::make()->success()->title(__('notification_events.announcement_saved'))->send();
            });
    }
}
