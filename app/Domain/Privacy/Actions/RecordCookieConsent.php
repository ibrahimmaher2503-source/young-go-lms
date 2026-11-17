<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Actions;

use App\Domain\Privacy\CookieConsent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Records a cookie decision. Guests are remembered only by the first-party cookie the caller sets from the returned value (no
 * personal data is stored for them). For a signed-in adult the decision is also kept in `consents` (type cookies, wording
 * version, granted_at); declining or withdrawing sets withdrawn_at on every active grant, so the history stays per version.
 * Idempotent: accepting twice for the same version keeps one active row.
 */
final class RecordCookieConsent
{
    public const string TYPE = 'cookies';

    public function __invoke(bool $accept, ?User $user): CookieConsent
    {
        if ($user !== null) {
            DB::transaction(function () use ($accept, $user): void {
                $version = CookieConsent::version();
                $active = static fn () => DB::table('consents')->where('user_id', $user->getKey())->where('type', self::TYPE)->whereNull('withdrawn_at');

                if ($accept && $active()->where('version', $version)->exists()) {
                    return;
                }

                $active()->update(['withdrawn_at' => now()]);

                if ($accept) {
                    DB::table('consents')->insert(['user_id' => $user->getKey(), 'type' => self::TYPE, 'version' => $version, 'granted_at' => now()]);
                }
            });
        }

        return CookieConsent::decision($accept);
    }
}
