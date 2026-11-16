<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Identity\Actions\AuthenticateAccount;
use App\Domain\Identity\Actions\RegisterAccount;
use App\Domain\Identity\Actions\ResetAccountPassword;
use App\Domain\Identity\Actions\VerifyAccountEmail;
use App\Domain\Identity\AuthMail;
use App\Http\Validation\NewPassword;
use App\Models\User;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;

final class AuthenticationController
{
    public function register(Request $request, RegisterAccount $register): Response
    {
        $terms = Config::get('identity.terms_version');
        $privacy = Config::get('identity.privacy_version');
        abort_unless(is_string($terms) && trim($terms) !== '' && is_string($privacy) && trim($privacy) !== '', 503, __('auth.registration_unavailable'));

        $request->merge([
            'email' => $this->normalizeIdentifier($request->input('email')),
            'username' => $this->normalizeIdentifier($request->input('username')),
        ]);

        $validated = $request->validate([
            'email' => ['required', 'string', 'max:255', 'email', 'unique:users,email'],
            'username' => ['nullable', 'string', 'max:64', 'alpha_dash:ascii', 'unique:users,username'],
            'password' => NewPassword::rules(),
            'name_ar' => ['nullable', 'required_without:name_en', 'string', 'max:255'],
            'name_en' => ['nullable', 'required_without:name_ar', 'string', 'max:255'],
            'birth_year' => ['nullable', 'required_with:birth_month', 'integer', 'between:1900,'.now()->year],
            'birth_month' => ['nullable', 'required_with:birth_year', 'integer', 'between:1,12'],
            'terms_version' => ['required', 'string', Rule::in([$terms])],
            'privacy_version' => ['required', 'string', Rule::in([$privacy])],
            'accept_terms' => ['accepted'],
            'accept_privacy' => ['accepted'],
        ]);

        $user = $register->handle([
            'email' => $validated['email'],
            'username' => $validated['username'] ?? null,
            'password' => $validated['password'],
            'name_ar' => $validated['name_ar'] ?? null,
            'name_en' => $validated['name_en'] ?? null,
            'birth_year' => isset($validated['birth_year']) ? (int) $validated['birth_year'] : null,
            'birth_month' => isset($validated['birth_month']) ? (int) $validated['birth_month'] : null,
        ], $terms, $privacy);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response('', 201);
    }

    public function login(Request $request, AuthenticateAccount $authenticate): Response
    {
        $validated = $request->validate(['login' => ['required', 'string', 'max:255'], 'password' => ['required', 'string', NewPassword::byteLimit(...)]]);
        $authenticate->handle(mb_strtolower(trim($validated['login'])), $validated['password']);
        $request->session()->regenerate();

        return response()->noContent();
    }

    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function forgotPassword(Request $request): Response
    {
        $validated = $request->validate(['email' => ['required', 'string', 'max:255', 'email']]);
        if (AuthMail::isSafe()) {
            Password::sendResetLink(['email' => mb_strtolower(trim($validated['email'])), 'status' => 'active']);
        }

        return response()->noContent();
    }

    public function resetPassword(Request $request, ResetAccountPassword $reset): Response
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'max:255', 'email'],
            'token' => ['required', 'string', 'max:255'],
            'password' => NewPassword::rules(),
        ]);
        $reset->handle(mb_strtolower(trim($validated['email'])), $validated['token'], $validated['password']);

        return response()->noContent();
    }

    public function resendVerification(Request $request): Response
    {
        $user = $request->user();
        if ($user instanceof User && $user->email !== null && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->noContent();
    }

    public function verifyEmail(EmailVerificationRequest $request, VerifyAccountEmail $verify): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->email !== null, 403);
        $verify->handle($user);

        return response()->noContent();
    }

    private function normalizeIdentifier(mixed $value): mixed
    {
        return is_string($value) ? mb_strtolower(trim($value)) : $value;
    }
}
