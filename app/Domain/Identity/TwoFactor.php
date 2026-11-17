<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PragmaRX\Google2FA\Google2FA;
use SensitiveParameter;

/** Thin TOTP helper (RFC 6238, SHA-1, 6 digits, 30 s) so Actions never touch the library directly. */
final class TwoFactor
{
    public const int RECOVERY_CODE_COUNT = 8;

    public function __construct(private readonly Google2FA $google2fa) {}

    public function newSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function provisioningUri(string $account, #[SensitiveParameter] string $secret): string
    {
        return $this->google2fa->getQRCodeUrl((string) config('app.name'), $account, $secret);
    }

    public function qrSvg(string $uri): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(192, 1), new SvgImageBackEnd));

        return $writer->writeString($uri);
    }

    /** Returns the matched time step, or null. Steps at or below $lastStep are rejected (replay protection). */
    public function verify(#[SensitiveParameter] string $secret, string $code, ?int $lastStep): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (preg_match('/^\d{6}$/', $code) !== 1) {
            return null;
        }

        // A null old timestamp makes the library return true instead of the step, so start from 0.
        $step = $this->google2fa->verifyKeyNewer($secret, $code, $lastStep ?? 0, 1);

        return is_int($step) ? $step : null;
    }

    /** @return list<string> plain codes, shown once */
    public function newRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
            $codes[] = strtolower(bin2hex(random_bytes(2)).'-'.bin2hex(random_bytes(2)));
        }

        return $codes;
    }

    public static function hashRecoveryCode(string $code): string
    {
        return hash_hmac('sha256', strtolower(trim($code)), (string) config('app.key'));
    }

    public static function normalizeRecoveryCode(string $code): string
    {
        return strtolower(trim($code));
    }
}
