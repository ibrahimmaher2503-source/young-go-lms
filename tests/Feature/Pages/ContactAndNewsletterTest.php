<?php

declare(strict_types=1);

use App\Domain\Content\Actions\AssignContactEnquiry;
use App\Domain\Content\Actions\ChangeContactEnquiryStatus;
use App\Domain\Content\Actions\RecordLeadExport;
use App\Domain\Content\Actions\UnsubscribeFromNewsletter;
use App\Domain\Content\ContactEnquiryStatus;
use App\Models\ContactEnquiry;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('stores validated contact enquiries without PII in audit logs, drops honeypot spam silently, and enforces rate limits and staff inbox actions', function (): void {
    // Honeypot filled -> 204 but stores nothing
    $honeypot = config('contact.honeypot_field', 'website');
    $this->post(route('contact.store'), [
        'name' => 'Bot',
        'email' => 'bot@spam.example.com',
        'message' => 'This is spam content from bot.',
        $honeypot => 'http://spam.example.com',
    ])->assertNoContent();
    expect(ContactEnquiry::query()->count())->toBe(0);

    // Validation rejects short message / invalid phone / invalid email
    $this->post(route('contact.store'), [
        'name' => 'Nour',
        'email' => 'not-an-email',
        'message' => 'short',
    ])->assertSessionHasErrors(['email', 'message']);

    // Valid enquiry stored + audit recorded WITHOUT email/phone/message PII
    RateLimiter::clear('contact|m|127.0.0.1');
    RateLimiter::clear('contact|h|127.0.0.1');
    $this->post(route('contact.store'), [
        'name' => 'Mariam Ali',
        'email' => 'MARIAM@EXAMPLE.COM',
        'phone' => '+201012345678',
        'message' => 'Hello, I want details about the watercolor track.',
    ])->assertNoContent();

    $enquiry = ContactEnquiry::query()->sole();
    expect($enquiry->email)->toBe('mariam@example.com')
        ->and($enquiry->status)->toBe(ContactEnquiryStatus::New);

    $audit = Activity::query()->where('event', 'contact.enquiry_received')->latest('id')->firstOrFail();
    expect(json_encode($audit->properties))->not->toContain('mariam@example.com')
        ->not->toContain('+201012345678')
        ->not->toContain('watercolor track');

    // Rate limit per minute kicks in with 429 on repeated requests from a dedicated address
    $last = null;
    for ($i = 0; $i < 4; $i++) {
        $last = $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])->post(route('contact.store'), [
            'name' => 'Spammer '.$i,
            'email' => "user{$i}@example.com",
            'message' => 'Valid length message body number '.$i,
        ]);
    }
    expect($last?->getStatusCode())->toBe(429);

    // Staff inbox workflow & authorization
    $handler = User::factory()->create(['status' => 'active'])->assignRole('marketing');
    $student = User::factory()->create(['status' => 'active'])->assignRole('student');

    expect(fn () => app(ChangeContactEnquiryStatus::class)($student, $enquiry, ContactEnquiryStatus::Resolved))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(AssignContactEnquiry::class)($handler, $enquiry, $student))
        ->toThrow(ValidationException::class);

    app(AssignContactEnquiry::class)($handler, $enquiry, $handler);
    app(ChangeContactEnquiryStatus::class)($handler, $enquiry->fresh(), ContactEnquiryStatus::Resolved);
    app(RecordLeadExport::class)($handler, 'contact_enquiries');

    expect($enquiry->fresh()->assigned_to_id)->toBe($handler->id)
        ->and($enquiry->fresh()->status)->toBe(ContactEnquiryStatus::Resolved)
        ->and($enquiry->fresh()->handled_at)->not->toBeNull()
        ->and(Activity::query()->where('event', 'lead.exported')->count())->toBe(1);
})->group('T-P2-08', 'A1', 'YG-WEB-007');

it('handles newsletter consent, honeypot, rate limits, identical duplicate responses, and login-free signed unsubscribe', function (): void {
    $honeypot = config('contact.honeypot_field', 'website');

    // Missing consent fails validation
    $this->post(route('newsletter.store'), ['email' => 'news@example.com'])
        ->assertSessionHasErrors(['consent']);

    // Honeypot dropped silently
    $this->post(route('newsletter.store'), ['email' => 'bot@example.com', 'consent' => '1', $honeypot => 'gotcha'])
        ->assertNoContent();
    expect(NewsletterSubscriber::query()->count())->toBe(0);

    // Subscribe -> normalized email + consent version; duplicate returns same 204 without extra row
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.20'])->post(route('newsletter.store'), ['email' => '  Reader@Example.COM ', 'consent' => '1', 'source' => 'footer'])
        ->assertNoContent();
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.20'])->post(route('newsletter.store'), ['email' => 'reader@example.com', 'consent' => '1', 'source' => 'home'])
        ->assertNoContent();

    $sub = NewsletterSubscriber::query()->sole();
    expect($sub->email)->toBe('reader@example.com')
        ->and($sub->source)->toBe('footer')
        ->and($sub->consent_version)->toBe(config('contact.newsletter_consent_version'))
        ->and($sub->isSubscribed())->toBeTrue();

    // Tampered unsubscribe URL rejected (403); signed URL unsubscribes without login cleanly & idempotently
    $signed = $sub->unsubscribeUrl();
    $this->get($signed.'tampered')->assertStatus(403);
    expect($sub->fresh()->isSubscribed())->toBeTrue();

    $this->get($signed)->assertOk();
    expect($sub->fresh()->isSubscribed())->toBeFalse()
        ->and($sub->fresh()->unsubscribed_at)->not->toBeNull();

    // Re-subscribing later clears unsubscribed_at
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.21'])->post(route('newsletter.store'), ['email' => 'reader@example.com', 'consent' => '1'])
        ->assertNoContent();
    expect($sub->fresh()->isSubscribed())->toBeTrue();

    // Rate limit enforcement
    $last = null;
    for ($i = 0; $i < 4; $i++) {
        $last = $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.22'])->post(route('newsletter.store'), ['email' => "n{$i}@example.com", 'consent' => '1']);
    }
    expect($last?->getStatusCode())->toBe(429);

    // Direct staff unsubscribe needs permission
    $student = User::factory()->create(['status' => 'active'])->assignRole('student');
    expect(fn () => app(UnsubscribeFromNewsletter::class)($sub->fresh(), $student))
        ->toThrow(AuthorizationException::class);
})->group('T-P2-08', 'A1', 'A2', 'YG-WEB-008');
