<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Identity\Actions\BeginTwoFactorEnrollment;
use App\Domain\Identity\Actions\ConfirmTwoFactorEnrollment;
use App\Domain\Identity\Actions\DisableTwoFactor;
use App\Domain\Identity\Actions\RegenerateRecoveryCodes;
use App\Domain\Identity\Actions\VerifyTwoFactorChallenge;
use App\Domain\Identity\TwoFactor;
use App\Domain\Identity\TwoFactorSession;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class TwoFactorController
{
    public function challengePage(Request $request): View|RedirectResponse
    {
        $user = $this->user($request);
        if (! $user->hasTwoFactorEnabled() || TwoFactorSession::isVerified($user)) {
            return redirect('/home');
        }

        return view('pages.two-factor-challenge');
    }

    public function challenge(Request $request, VerifyTwoFactorChallenge $verify): Response
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:32']]);
        $verified = $verify->handle($this->user($request), $data['code']);
        TwoFactorSession::markVerified($verified);
        $request->session()->regenerate();

        return response()->noContent();
    }

    public function setupPage(Request $request, BeginTwoFactorEnrollment $begin, TwoFactor $twoFactor): Response
    {
        $user = $this->user($request);
        $enabled = $user->hasTwoFactorEnabled();
        $secret = $enabled ? null : $begin->handle($user);
        $account = (string) ($user->email ?? $user->username ?? $user->getKey());

        return response()->view('pages.two-factor-setup', [
            'enabled' => $enabled,
            'required' => $user->requiresTwoFactor(),
            'secret' => $secret,
            'qr' => $secret === null ? null : $twoFactor->qrSvg($twoFactor->provisioningUri($account, $secret)),
            'remaining' => is_array($user->two_factor_recovery_codes) ? count($user->two_factor_recovery_codes) : 0,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function confirm(Request $request, ConfirmTwoFactorEnrollment $confirm): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:16']]);
        $user = $this->user($request);
        // Re-authenticate so a hijacked session cannot enroll the attacker's own authenticator.
        $this->confirmPassword($request, $user);
        ['user' => $verified, 'recoveryCodes' => $codes] = $confirm->handle($user, $data['code']);
        // Sync the authenticated model to the enrollment that accepted the code.
        $user->setRawAttributes($verified->getAttributes(), true);
        TwoFactorSession::markVerified($verified);
        $request->session()->regenerate();

        return response()->json(['recovery_codes' => $codes])->header('Cache-Control', 'no-store');
    }

    public function regenerate(Request $request, RegenerateRecoveryCodes $regenerate): JsonResponse
    {
        $user = $this->user($request);
        $this->confirmPassword($request, $user);

        return response()->json(['recovery_codes' => $regenerate->handle($user)])->header('Cache-Control', 'no-store');
    }

    public function disable(Request $request, DisableTwoFactor $disable): Response
    {
        $user = $this->user($request);
        $this->confirmPassword($request, $user);
        try {
            $disable->handle($user, (int) $user->getKey());
        } catch (AuthorizationException) {
            abort(403, __('auth.two_factor_cannot_disable'));
        }

        return response()->noContent();
    }

    private function confirmPassword(Request $request, User $user): void
    {
        $data = $request->validate(['password' => ['required', 'string', 'max:255']]);
        if (! Hash::check($data['password'], (string) $user->getAuthPassword())) {
            throw ValidationException::withMessages(['password' => __('auth.password')]);
        }
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
