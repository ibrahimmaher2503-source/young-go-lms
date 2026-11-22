<?php

declare(strict_types=1);

namespace App\Filament\Resources\NotificationTemplates;

use App\Domain\Notifications\Actions\SaveNotificationTemplate;
use App\Domain\Notifications\NotificationEventDefinition;
use App\Domain\Notifications\NotificationMatrix;
use App\Domain\Notifications\NotificationTemplate;
use App\Domain\Notifications\NotificationTemplates;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

/** Adds or edits wording for a matrix event through the same audited save action. */
final class NotificationTemplateForm
{
    public static function configure(Action $action, bool $adding = false): Action
    {
        $event = static function (?NotificationTemplate $record, Get $get): ?NotificationEventDefinition {
            $key = $record->event_key ?? $get('event_key');

            return is_string($key) ? NotificationMatrix::find($key) : null;
        };
        $help = static function (?NotificationEventDefinition $row): ?HtmlString {
            if ($row === null) {
                return null;
            }

            $tokens = $row->placeholders === [] ? '—' : ':'.implode(' :', $row->placeholders);

            return new HtmlString(__('notification_events.template_placeholders', ['tokens' => '<bdi dir="ltr">'.e($tokens).'</bdi>']));
        };
        $default = static function (?NotificationEventDefinition $row, string $field, string $locale): ?HtmlString {
            $key = $field === 'title' ? $row?->titleKey : $row?->bodyKey;
            if ($key === null) {
                return null;
            }

            $text = preg_replace('/:[a-z][a-z0-9_]*/i', '<bdi dir="ltr">$0</bdi>', e(__($key, [], $locale)));
            $direction = $locale === 'ar' ? 'rtl' : 'ltr';

            return new HtmlString(__('notification_events.template_default', ['text' => '<bdi dir="'.$direction.'" lang="'.$locale.'">'.$text.'</bdi>']));
        };

        if (! $adding) {
            $action->fillForm(static fn (NotificationTemplate $record): array => $record->only(['title_ar', 'title_en', 'body_ar', 'body_en', 'is_active']));
        }

        return $action->authorize('system.settings.manage')->schema([
            ...($adding ? [Select::make('event_key')->label(__('notification_events.col_event'))
                ->options(self::availableEvents(...))->noOptionsMessage(__('notification_events.template_no_events'))
                ->helperText(static fn (Get $get): ?HtmlString => $help($event(null, $get)))
                ->searchable()->required()->live()] : [Text::make(static fn (NotificationTemplate $record): ?HtmlString => $help(NotificationMatrix::find($record->event_key)))]),
            TextInput::make('title_ar')->label(__('notification_events.field_title_ar'))->maxLength(NotificationTemplates::TITLE_MAX)->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar'])
                ->helperText(static fn (?NotificationTemplate $record, Get $get): ?HtmlString => $default($event($record, $get), 'title', 'ar')),
            Textarea::make('body_ar')->label(__('notification_events.field_body_ar'))->rows(4)->maxLength(NotificationTemplates::BODY_MAX)->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar'])
                ->helperText(static fn (?NotificationTemplate $record, Get $get): ?HtmlString => $default($event($record, $get), 'body', 'ar')),
            TextInput::make('title_en')->label(__('notification_events.field_title_en'))->maxLength(NotificationTemplates::TITLE_MAX)->extraInputAttributes(['dir' => 'ltr', 'lang' => 'en'])
                ->helperText(static fn (?NotificationTemplate $record, Get $get): ?HtmlString => $default($event($record, $get), 'title', 'en')),
            Textarea::make('body_en')->label(__('notification_events.field_body_en'))->rows(4)->maxLength(NotificationTemplates::BODY_MAX)->extraInputAttributes(['dir' => 'ltr', 'lang' => 'en'])
                ->helperText(static fn (?NotificationTemplate $record, Get $get): ?HtmlString => $default($event($record, $get), 'body', 'en')),
            Toggle::make('is_active')->label(__('notification_events.field_active'))->default(true),
        ])->action(static function (array $data, Action $action, ?NotificationTemplate $record) use ($adding): void {
            $actor = Auth::user();
            abort_unless($actor instanceof User, 401);
            try {
                $key = $record->event_key ?? $data['event_key'] ?? '';
                app(SaveNotificationTemplate::class)($actor, $key, $data, onlyUncustomized: $adding);
            } catch (ValidationException $exception) {
                Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();
                $action->halt();

                return;
            }
            Notification::make()->success()->title(__('notification_events.template_saved'))->send();
        });
    }

    /** @return array<string, string> */
    private static function availableEvents(): array
    {
        $customized = NotificationTemplate::query()->get()->filter(static fn (NotificationTemplate $template): bool => $template->hasCustomWording())->keyBy('event_key');

        return collect(NotificationMatrix::all())
            ->filter(static fn (NotificationEventDefinition $row): bool => $row->editable() && ! $customized->has($row->key))
            ->map(static fn (NotificationEventDefinition $row): string => $row->label())->all();
    }
}
