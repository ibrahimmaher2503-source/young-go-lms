<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Identity\Models\GuardianChildLink;
use App\Models\User;
use DateTimeZone;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

final class PageController
{
    public function login(): View|RedirectResponse
    {
        return $this->guestOnly() ?? view('pages.login');
    }

    public function register(): View|RedirectResponse
    {
        $terms = Config::get('identity.terms_version');
        $privacy = Config::get('identity.privacy_version');
        $available = is_string($terms) && trim($terms) !== '' && is_string($privacy) && trim($privacy) !== '';

        return $this->guestOnly() ?? view('pages.register', [
            'available' => $available,
            'termsVersion' => $available ? $terms : '',
            'privacyVersion' => $available ? $privacy : '',
        ]);
    }

    public function forgotPassword(): View|RedirectResponse
    {
        return $this->guestOnly() ?? view('pages.forgot-password');
    }

    public function resetPassword(Request $request, string $token): Response|RedirectResponse
    {
        $email = $request->query('email');

        return $this->guestOnly() ?? response()
            ->view('pages.reset-password', ['token' => $token, 'email' => is_string($email) ? $email : ''])
            ->header('Cache-Control', 'no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function verifyNotice(Request $request): View|RedirectResponse
    {
        $user = $this->user($request);
        if ($user->email === null || $user->hasVerifiedEmail()) {
            return redirect('/home');
        }

        return view('pages.verify-email', ['email' => $user->email]);
    }

    public function home(Request $request): RedirectResponse
    {
        $user = $this->user($request);

        foreach (['admin', 'instructor', 'finance'] as $panel) {
            if ($user->can("panel.{$panel}.access")) {
                return redirect('/'.$panel);
            }
        }

        return redirect($user->hasRole('parent') ? '/family' : '/profile');
    }

    public function profile(Request $request): View
    {
        $user = $this->user($request);
        $preferences = json_decode((string) $user->getAttribute('notification_preferences'), true);
        $zones = array_values(array_unique(array_filter([
            'Africa/Cairo', 'Asia/Riyadh', 'Asia/Dubai', 'Europe/London', 'UTC', (string) $user->getAttribute('timezone'),
        ])));
        $zones = array_values(array_filter($zones, static fn (string $zone): bool => in_array($zone, DateTimeZone::listIdentifiers(), true) || $zone === 'UTC'));

        return view('pages.profile', [
            'user' => $user,
            'variant' => $user->hasRole('parent') ? 'parent' : 'student',
            'timezones' => $zones,
            'emailNotifications' => ! is_array($preferences) || ($preferences['email'] ?? true) !== false,
        ]);
    }

    public function family(Request $request): View
    {
        $parent = $this->user($request);
        abort_unless($parent->hasRole('parent') && $parent->can('guardian.child.manage'), 403);

        $links = GuardianChildLink::query()->with('child')->where('guardian_id', $parent->getKey())->orderBy('id')->get();

        return view('pages.family', [
            'children' => $links->where('status', GuardianChildLink::STATUS_VERIFIED)->values(),
            'pending' => $links->where('status', GuardianChildLink::STATUS_PENDING)->values(),
        ]);
    }

    public function link(Request $request): View
    {
        $learner = $this->user($request);
        abort_unless($learner->hasRole('student'), 403);

        return view('pages.link', [
            'pending' => GuardianChildLink::query()->with('guardian')
                ->where('child_id', $learner->getKey())->where('status', GuardianChildLink::STATUS_PENDING)->orderBy('id')->get(),
        ]);
    }

    private function guestOnly(): ?RedirectResponse
    {
        return Auth::check() ? redirect('/home') : null;
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
