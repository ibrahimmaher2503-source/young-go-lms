<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Identity\Actions\ChangeProfilePassword;
use App\Domain\Identity\Actions\UpdateAvatar;
use App\Domain\Identity\Actions\UpdateCertificateNames;
use App\Domain\Identity\Actions\UpdateProfile;
use App\Http\Validation\NewPassword;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ProfileController
{
    public function update(Request $request, UpdateProfile $action): Response
    {
        $data = $request->validate([
            'name_ar' => ['sometimes', 'nullable', 'string', 'max:255'],
            'name_en' => ['sometimes', 'nullable', 'string', 'max:255'],
            'display_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'locale' => ['sometimes', 'required', 'string', Rule::in(Config::array('app.supported_locales'))],
            'timezone' => ['sometimes', 'required', 'timezone'],
            'notification_preferences' => ['sometimes', 'array:email'],
            'notification_preferences.email' => ['sometimes', 'boolean'],
        ], $this->messages());

        $action->handle($this->user($request), $data);

        return response()->noContent();
    }

    public function certificateNames(Request $request, UpdateCertificateNames $action): Response
    {
        $data = $request->validate([
            'certificate_name_ar' => ['sometimes', 'nullable', 'string', 'max:255'],
            'certificate_name_en' => ['sometimes', 'nullable', 'string', 'max:255'],
        ], $this->messages());
        $action->handle($this->user($request), $data);

        return response()->noContent();
    }

    public function password(Request $request, ChangeProfilePassword $action): Response
    {
        $data = $request->validate([
            'current_password' => ['required', 'string', NewPassword::byteLimit(...)],
            'password' => NewPassword::rules(),
        ], $this->messages());
        $user = $this->user($request);
        $action->handle($user, $data['current_password'], $data['password']);
        $user->refresh();
        $request->session()->regenerate(true);

        return response()->noContent();
    }

    public function avatar(Request $request, UpdateAvatar $action): Response
    {
        $request->validate(['avatar' => ['required', 'file', 'image', 'mimes:jpeg,png,webp', 'max:4096', 'dimensions:max_width=2048,max_height=2048']], $this->messages());
        $avatar = $request->file('avatar');
        if (! $avatar instanceof UploadedFile || ! is_string($bytes = $avatar->get())) {
            throw ValidationException::withMessages(['avatar' => __('profile.invalid_avatar')]);
        }

        $action->handle($this->user($request), $bytes);

        return response()->noContent();
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'max' => __('profile.invalid_value'),
            'boolean' => __('profile.invalid_value'),
            'array' => __('profile.invalid_value'),
            'timezone' => __('profile.invalid_value'),
            'confirmed' => __('profile.password_confirmation'),
            'min' => __('profile.invalid_value'),
            'avatar.*' => __('profile.invalid_avatar'),
        ];
    }
}
