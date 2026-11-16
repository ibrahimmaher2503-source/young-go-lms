<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Identity\Actions\UpdatePreferredLocale;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Rule;

final class LocaleController
{
    public function __invoke(Request $request, UpdatePreferredLocale $updatePreferredLocale): RedirectResponse
    {
        $validated = $request->validate(['locale' => ['required', 'string', Rule::in(Config::array('app.supported_locales'))]]);
        $locale = $validated['locale'];

        $user = $request->user();
        if ($user instanceof User) {
            $updatePreferredLocale->handle($user, $locale);
        }

        $request->session()->put('locale', $locale);

        return redirect('/', 303)->withCookie(cookie('locale', $locale, 60 * 24 * 365));
    }
}
