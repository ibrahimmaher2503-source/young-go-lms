<?php

declare(strict_types=1);

namespace App\Filament\Resources\NotificationTemplates;

use App\Domain\Notifications\Actions\SaveNotificationTemplate;
use App\Domain\Notifications\NotificationMatrix;
use App\Domain\Notifications\NotificationTemplate;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/** The edit modal for one template. Shows the allowed placeholders and the shipped default; the save goes through SaveNotificationTemplate. */
final class NotificationTemplateForm
{
    public static function configure(Action $action): Action
    {
        $help = static function (?NotificationTemplate $record): string {
            $row = NotificationMatrix::find($record?->event_key);

            return $row === null ? '' : __('notification_events.template_placeholders', ['tokens' => $row->placeholders === [] ? '-' : implode(' ', array_map(static fn (string $t): string => ':'.$t, $row->placeholders))]);
        };
        $default = static function (?NotificationTemplate $record, string $field, string $locale): string {
            $row = NotificationMatrix::find($record?->event_key);
            $key = $field === 'title' ? $row?->titleKey : $row?->bodyKey;

            return $key === null ? '' : __('notification_events.template_default', ['text' => (string) __($key, [], $locale)]);
        };

        return $action->schema([
            TextInput::make('title_ar')->label(__('notification_events.field_title_ar'))->maxLength(160)->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar'])
                ->helperText(static fn (?NotificationTemplate $record): string => $default($record, 'title', 'ar')),
            Textarea::make('body_ar')->label(__('notification_events.field_body_ar'))->rows(4)->maxLength(1000)->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar'])
                ->helperText(static fn (?NotificationTemplate $record): string => $default($record, 'body', 'ar')),
            TextInput::make('title_en')->label(__('notification_events.field_title_en'))->maxLength(160)->extraInputAttributes(['dir' => 'ltr', 'lang' => 'en'])
                ->helperText(static fn (?NotificationTemplate $record): string => $default($record, 'title', 'en')),
            Textarea::make('body_en')->label(__('notification_events.field_body_en'))->rows(4)->maxLength(1000)->extraInputAttributes(['dir' => 'ltr', 'lang' => 'en'])
                ->helperText(static fn (?NotificationTemplate $record): string => $default($record, 'body', 'en').' '.$help($record)),
            Toggle::make('is_active')->label(__('notification_events.field_active')),
        ])->fillForm(static fn (NotificationTemplate $record): array => $record->only(['title_ar', 'title_en', 'body_ar', 'body_en', 'is_active']))
            ->action(static function (array $data, Action $action, NotificationTemplate $record): void {
                $actor = Auth::user();
                abort_unless($actor instanceof User, 401);
                try {
                    app(SaveNotificationTemplate::class)($actor, $record->event_key, $data);
                } catch (ValidationException $exception) {
                    Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();
                    $action->halt();

                    return;
                }
                Notification::make()->success()->title(__('notification_events.template_saved'))->send();
            });
    }
}
