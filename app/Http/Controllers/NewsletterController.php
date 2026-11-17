<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Content\Actions\SubscribeToNewsletter;
use App\Domain\Content\Actions\UnsubscribeFromNewsletter;
use App\Models\NewsletterSubscriber;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;

/** Public newsletter subscribe form and the signed, login-free unsubscribe link (YG-WEB-008). */
final class NewsletterController
{
    public function show(): View
    {
        return view('newsletter.show');
    }

    public function store(Request $request, SubscribeToNewsletter $subscribe): Response
    {
        if (filled($request->input(Config::string('contact.honeypot_field')))) {
            return response()->noContent();
        }

        $validated = $request->validate([
            'email' => ['required', 'string', 'max:255', 'email'],
            'consent' => ['accepted'],
            'source' => ['nullable', 'string', 'max:24'],
        ], [
            'consent.accepted' => __('contact.errors.consent'),
        ], [
            'email' => __('contact.fields.email'),
        ]);

        $subscribe($validated['email'], app()->getLocale(), (string) ($validated['source'] ?? ''));

        // Same answer for a new, an existing and a re-subscribed address.
        return response()->noContent();
    }

    /** The route is `signed`: an altered or foreign link never reaches here. */
    public function unsubscribe(NewsletterSubscriber $subscriber, UnsubscribeFromNewsletter $unsubscribe): View
    {
        $unsubscribe($subscriber);

        return view('newsletter.unsubscribed');
    }
}
