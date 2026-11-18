<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Notifications\Actions\SetPhoneReminderOptIn;
use App\Domain\Notifications\Actions\UpdateNotificationPreferences;
use App\Domain\Notifications\NotificationMatrix;
use App\Domain\Notifications\Queries\NotificationPreferenceOverview;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** T-P9-05: the learner's notification preferences page. Only optional notices can be switched; mandatory ones are listed as always sent. */
final class NotificationPreferencesController
{
    public function show(Request $request, NotificationPreferenceOverview $overview): View
    {
        $user = $this->user($request);

        return view('pages.notification-preferences', [
            'user' => $user,
            'variant' => $user->hasRole('parent') ? 'parent' : 'student',
            'overview' => $overview->for($user),
        ]);
    }

    public function update(Request $request, UpdateNotificationPreferences $action): Response
    {
        $data = $request->validate([
            'enabled' => ['sometimes', 'array'],
            'enabled.*' => ['boolean'],
            'phone' => ['sometimes', 'array'],
            'phone.*' => ['boolean'],
        ], ['boolean' => __('profile.invalid_value'), 'array' => __('profile.invalid_value')]);

        $enabled = [];
        foreach ((array) ($data['enabled'] ?? []) as $formKey => $value) {
            $row = NotificationMatrix::fromFormKey((string) $formKey);
            if ($row === null) {
                throw ValidationException::withMessages(['enabled' => __('notification_events.cannot_opt_out')]);
            }
            $enabled[$row->key] = (bool) $value;
        }

        $phone = [];
        foreach ((array) ($data['phone'] ?? []) as $channel => $value) {
            if (! in_array($channel, SetPhoneReminderOptIn::CHANNELS, true)) {
                throw ValidationException::withMessages(['phone' => __('notification_events.phone_unavailable')]);
            }
            $phone[(string) $channel] = (bool) $value;
        }

        $action($this->user($request), $enabled, $phone);

        return response()->noContent();
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
