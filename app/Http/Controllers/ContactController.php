<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Content\Actions\SubmitContactEnquiry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;

/** Public contact form (YG-WEB-007): honeypot, rate limit (route throttle), then the stored enquiry. */
final class ContactController
{
    public function show(): View
    {
        return view('contact.show');
    }

    public function store(Request $request, SubmitContactEnquiry $submit): Response
    {
        // A filled decoy field means a bot: answer like a success and store nothing.
        if (filled($request->input(Config::string('contact.honeypot_field')))) {
            return response()->noContent();
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'max:255', 'email'],
            'phone' => ['nullable', 'string', 'max:32'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
        ], [], [
            'name' => __('contact.fields.name'),
            'email' => __('contact.fields.email'),
            'phone' => __('contact.fields.phone'),
            'message' => __('contact.fields.message'),
        ]);

        $submit($validated, app()->getLocale());

        return response()->noContent();
    }
}
