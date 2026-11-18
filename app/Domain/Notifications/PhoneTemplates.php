<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

use Illuminate\Support\Facades\Lang;

/** Phone message templates keyed by event (lang/{ar,en}/phone_templates.php). An unknown event or locale has no template and sends nothing. */
final class PhoneTemplates
{
    public static function render(string $template, string $locale, string $message): ?string
    {
        $key = "phone_templates.{$template}";
        if (! in_array($locale, ['ar', 'en'], true) || ! Lang::has($key, $locale, false)) {
            return null;
        }

        $text = __($key, ['message' => $message], $locale);

        return is_string($text) ? $text : null;
    }
}
