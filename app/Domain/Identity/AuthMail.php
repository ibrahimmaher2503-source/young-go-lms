<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\Config;

final class AuthMail
{
    public static function isSafe(): bool
    {
        if (Config::get('mail.driver') !== null) {
            return false;
        }

        return self::isSafeMailer(Config::get('mail.default'), []);
    }

    /** @param  list<string>  $ancestors */
    private static function isSafeMailer(mixed $name, array $ancestors): bool
    {
        if (! is_string($name) || in_array($name, $ancestors, true)) {
            return false;
        }

        $mailer = Config::get("mail.mailers.{$name}");
        if (! is_array($mailer)) {
            return false;
        }

        $transport = $mailer['transport'] ?? null;
        if (isset($mailer['url'])) {
            $transport = (new ConfigurationUrlParser)->parseConfiguration($mailer)['driver'] ?? null;
        }

        if (in_array($transport, ['failover', 'roundrobin'], true)) {
            $branches = $mailer['mailers'] ?? [];

            return is_array($branches) && $branches !== []
                && array_all($branches, static fn (mixed $branch): bool => self::isSafeMailer($branch, [...$ancestors, $name]));
        }

        return in_array($transport, ['array', 'smtp', 'sendmail', 'ses', 'postmark', 'resend'], true);
    }
}
