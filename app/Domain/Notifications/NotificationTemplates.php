<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

use Illuminate\Validation\ValidationException;

/**
 * Renders and validates admin-editable notification wording. Templates use `:token` placeholders from the matrix whitelist
 * only: values are substituted as plain text (never Blade, never HTML), so a template cannot run code or inject markup.
 */
final class NotificationTemplates
{
    public const int TITLE_MAX = 160;

    public const int BODY_MAX = 1000;

    private const string TOKEN = '/(?<![\p{L}\p{N}]):([a-z][a-z_]*)/u';

    /**
     * The active override for this event and locale, or $default. Values outside the whitelist are never substituted.
     *
     * @param  array<string, scalar|null>  $values
     */
    public static function render(string $event, string $field, array $values, string $default, ?string $locale = null): string
    {
        $row = NotificationMatrix::find($event);
        if ($row === null || ! $row->editable()) {
            return $default;
        }

        $locale ??= app()->getLocale();
        $locale = $locale === 'ar' ? 'ar' : 'en';
        $template = NotificationTemplate::query()->where('event_key', $event)->where('is_active', true)->first();
        $text = $template?->getAttribute($field.'_'.$locale);
        if (! is_string($text) || trim($text) === '') {
            return $default;
        }

        $replace = [];
        foreach ($row->placeholders as $token) {
            $replace[':'.$token] = (string) ($values[$token] ?? '');
        }

        return strtr($text, $replace);
    }

    /** @return list<string> tokens used by the text that the event does not allow */
    public static function unknownTokens(string $text, NotificationEventDefinition $row): array
    {
        preg_match_all(self::TOKEN, $text, $found);

        return array_values(array_diff(array_unique($found[1]), $row->placeholders));
    }

    /**
     * Cleans one template text or refuses it. Blank means "use the default".
     *
     * @throws ValidationException
     */
    public static function clean(?string $text, NotificationEventDefinition $row, string $field, int $max): ?string
    {
        $text = $text === null ? '' : trim($text);
        if ($text === '') {
            return null;
        }
        if (mb_strlen($text) > $max) {
            throw ValidationException::withMessages([$field => __('notification_events.template_too_long', ['max' => $max])]);
        }
        if (preg_match('/[<>]|\{\{|\{!!|@php/u', $text) === 1) {
            throw ValidationException::withMessages([$field => __('notification_events.template_markup')]);
        }
        $unknown = self::unknownTokens($text, $row);
        if ($unknown !== []) {
            throw ValidationException::withMessages([$field => __('notification_events.template_unknown_token', ['tokens' => implode(', ', array_map(static fn (string $t): string => ':'.$t, $unknown))])]);
        }

        return $text;
    }
}
