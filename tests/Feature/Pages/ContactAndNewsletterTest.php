<?php

declare(strict_types=1);

use App\Domain\Content\Actions\AssignContactEnquiry;
use App\Domain\Content\Actions\ChangeContactEnquiryStatus;
use App\Domain\Content\Actions\RecordLeadExport;
use App\Domain\Content\Actions\UnsubscribeFromNewsletter;
use App\Domain\Content\ContactEnquiryStatus;
use App\Domain\Settings\GeneralSettings;
use App\Filament\Resources\ContactEnquiries\Pages\ListContactEnquiries;
use App\Filament\Resources\NewsletterSubscribers\Pages\ListNewsletterSubscribers;
use App\Models\ContactEnquiry;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('shows configured contact channels and keeps the contact and newsletter forms', function (): void {
    $this->withoutVite();
    $settings = app(GeneralSettings::class);
    $settings->contact_email = null;
    $settings->contact_phone = null;
    $settings->save();
    $this->get('/ar/contact')->assertOk()->assertDontSee('href="tel:', false);

    $settings->contact_email = 'help@example.test';
    $settings->contact_phone = '+20 10 1234 5678';
    $settings->save();
    $this->get('/ar/contact')->assertOk()
        ->assertSee('data-info-page="contact"', false)
        ->assertSee('href="mailto:help@example.test"', false)
        ->assertSee('href="tel:+201012345678"', false)
        ->assertSee('action="'.url('/ar/contact').'"', false)
        ->assertSee('action="'.url('/ar/newsletter').'"', false)
        ->assertSee('name="consent"', false);
})->group('T-P2-08', 'A1', 'A2', 'YG-WEB-007', 'YG-WEB-008');

it('keeps the explicit contact language from the public page through submission', function (): void {
    $this->withoutVite()->withSession(['locale' => 'en']);

    $this->get('/ar/contact')->assertOk()
        ->assertSee('lang="ar" dir="rtl"', false)
        ->assertSee('action="'.url('/ar/contact').'"', false);
    $this->post('/ar/contact', [
        'name' => 'Mariam',
        'email' => 'mariam@example.test',
        'message' => 'Please send details about the drawing course.',
    ])->assertNoContent();

    expect(ContactEnquiry::query()->sole()->locale)->toBe('ar');
})->group('T-P2-03', 'T-P2-08', 'YG-WEB-007', 'YG-WEB-010');

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

function t208Enquiry(array $overrides = []): ContactEnquiry
{
    return ContactEnquiry::query()->create([
        'name' => 'Nour Hassan',
        'email' => 'nour@example.com',
        'phone' => null,
        'message' => 'I would like to know about the drawing classes.',
        'locale' => 'en',
        'status' => ContactEnquiryStatus::New,
        ...$overrides,
    ]);
}

function t208Subscriber(array $overrides = []): NewsletterSubscriber
{
    return NewsletterSubscriber::query()->create([
        'email' => 'reader@example.com',
        'locale' => 'en',
        'source' => 'footer',
        'consent_version' => 'newsletter-v1-draft',
        'consented_at' => now(),
        ...$overrides,
    ]);
}

it('lets marketing staff mount the contact inbox and search and filter it', function (): void {
    $marketing = User::factory()->create(['status' => 'active'])->assignRole('marketing');
    $handler = User::factory()->create(['status' => 'active', 'name' => 'Hana Handler'])->assignRole('marketing');
    $this->actingAs($marketing)->get(route('filament.admin.resources.contact-enquiries.index'))->assertOk();

    $watercolour = t208Enquiry(['name' => 'Layla Search', 'email' => 'layla@example.com', 'message' => 'Watercolour weekend workshop question']);
    $pottery = t208Enquiry(['name' => 'Omar Other', 'email' => 'omar@example.com', 'message' => 'Pottery schedule question', 'status' => ContactEnquiryStatus::Resolved, 'assigned_to_id' => $handler->id]);

    Livewire::actingAs($marketing)->test(ListContactEnquiries::class)
        ->searchTable('layla@example.com')
        ->assertCanSeeTableRecords([$watercolour])
        ->assertCanNotSeeTableRecords([$pottery])
        ->searchTable('Pottery schedule')
        ->assertCanSeeTableRecords([$pottery])
        ->assertCanNotSeeTableRecords([$watercolour])
        ->searchTable('')
        ->filterTable('status', 'resolved')
        ->assertCanSeeTableRecords([$pottery])
        ->assertCanNotSeeTableRecords([$watercolour])
        ->resetTableFilters()
        ->filterTable('assigned_to_id', $handler->id)
        ->assertCanSeeTableRecords([$pottery])
        ->assertCanNotSeeTableRecords([$watercolour]);
})->group('T-P2-08', 'A1', 'YG-WEB-007');

it('keeps the contact inbox and newsletter list closed to roles without marketing.lead.view', function (string $role): void {
    $user = User::factory()->create(['status' => 'active'])->assignRole($role);
    $user->givePermissionTo('panel.admin.access');
    t208Enquiry();

    $this->actingAs($user)->get(route('filament.admin.resources.contact-enquiries.index'))->assertForbidden();
    $this->actingAs($user)->get(route('filament.admin.resources.newsletter-subscribers.index'))->assertForbidden();
})->with(['admin', 'instructor_academy', 'student'])->group('T-P2-08', 'A1', 'A2', 'YG-WEB-007', 'YG-WEB-008');

it('shows enquiry details in the view modal with the message escaped', function (): void {
    $marketing = User::factory()->create(['status' => 'active'])->assignRole('marketing');
    $payload = '<script>alert("x")</script>';
    $enquiry = t208Enquiry(['message' => $payload.' please call me back']);

    Livewire::actingAs($marketing)->test(ListContactEnquiries::class)
        ->callTableAction('view', $enquiry)
        ->assertSee('please call me back')
        ->assertSee(e($payload), escape: false)
        ->assertDontSee($payload, escape: false);
})->group('T-P2-08', 'A1', 'YG-WEB-007');

it('assigns an enquiry to an active handler with an audit entry and rejects an inactive or non-handler assignee', function (): void {
    $marketing = User::factory()->create(['status' => 'active'])->assignRole('marketing');
    $handler = User::factory()->create(['status' => 'active'])->assignRole('marketing');
    $suspended = User::factory()->create(['status' => 'suspended'])->assignRole('marketing');
    $student = User::factory()->create(['status' => 'active'])->assignRole('student');
    $enquiry = t208Enquiry();

    Livewire::actingAs($marketing)->test(ListContactEnquiries::class)
        ->callTableAction('assign', $enquiry, data: ['assignee' => $handler->id])
        ->assertNotified(__('contact.admin.assigned'));

    expect($enquiry->fresh()->assigned_to_id)->toBe($handler->id);
    expect(Activity::query()->where('event', 'contact.enquiry_assigned')->where('subject_id', $enquiry->id)->count())->toBe(1);

    foreach ([$suspended, $student] as $ineligible) {
        // The inbox only offers active handlers, so the form rejects the choice before the action runs.
        Livewire::actingAs($marketing)->test(ListContactEnquiries::class)
            ->callTableAction('assign', $enquiry->fresh(), data: ['assignee' => $ineligible->id])
            ->assertHasTableActionErrors(['assignee']);

        expect($enquiry->fresh()->assigned_to_id)->toBe($handler->id);

        // The domain action enforces the same rule for any other caller.
        expect(fn () => app(AssignContactEnquiry::class)($marketing, $enquiry->fresh(), $ineligible))
            ->toThrow(ValidationException::class);

        expect($enquiry->fresh()->assigned_to_id)->toBe($handler->id);
    }

    expect(Activity::query()->where('event', 'contact.enquiry_assigned')->count())->toBe(1);
})->group('T-P2-08', 'A1', 'YG-WEB-007');

it('changes the enquiry status, stamps handled_at on resolve and clears it when moved back to in progress', function (): void {
    $marketing = User::factory()->create(['status' => 'active'])->assignRole('marketing');
    $enquiry = t208Enquiry();

    Livewire::actingAs($marketing)->test(ListContactEnquiries::class)
        ->callTableAction('status', $enquiry, data: ['status' => 'resolved'])
        ->assertNotified(__('contact.admin.status_changed'));

    expect($enquiry->fresh()->status)->toBe(ContactEnquiryStatus::Resolved)
        ->and($enquiry->fresh()->handled_at)->not->toBeNull();
    expect(Activity::query()->where('event', 'contact.enquiry_status_changed')->where('subject_id', $enquiry->id)->count())->toBe(1);

    Livewire::actingAs($marketing)->test(ListContactEnquiries::class)
        ->callTableAction('status', $enquiry->fresh(), data: ['status' => 'in_progress'])
        ->assertNotified(__('contact.admin.status_changed'));

    expect($enquiry->fresh()->status)->toBe(ContactEnquiryStatus::InProgress)
        ->and($enquiry->fresh()->handled_at)->toBeNull();
})->group('T-P2-08', 'A1', 'YG-WEB-007');

it('downloads a UTF-8 BOM enquiry CSV with localized headings, neutralised formulas, table filters and one audit entry', function (string $locale): void {
    $marketing = User::factory()->create(['status' => 'active', 'locale' => $locale])->assignRole('marketing');
    app()->setLocale($locale);
    t208Enquiry(['name' => '=2+2 Sara', 'email' => 'sara@example.com', 'phone' => '+201012345678', 'message' => 'Secret message about classes', 'status' => ContactEnquiryStatus::New]);
    t208Enquiry(['name' => 'Omar Other', 'email' => 'omar@example.com', 'message' => 'Resolved message', 'status' => ContactEnquiryStatus::Resolved]);

    $component = Livewire::actingAs($marketing)->test(ListContactEnquiries::class)
        ->filterTable('status', 'new')
        ->callAction(TestAction::make('exportCsv'))
        ->assertFileDownloaded('contact-enquiries.csv');

    $csv = base64_decode($component->effects['download']['content'], true);
    expect(str_starts_with((string) $csv, "\xEF\xBB\xBF"))->toBeTrue()
        ->and($csv)->toContain(__('contact.fields.name', [], $locale), __('contact.fields.email', [], $locale))
        ->and($csv)->toContain("'=2+2 Sara")
        ->and($csv)->not->toContain('Omar Other')
        ->and($csv)->not->toContain('Resolved message');

    $exports = Activity::query()->where('event', 'lead.exported')->get();
    expect($exports)->toHaveCount(1)
        ->and(json_encode($exports->first()->properties))->not->toContain('sara@example.com')
        ->not->toContain('+201012345678')
        ->not->toContain('Secret message');
})->with(['ar', 'en'])->group('T-P2-08', 'A1', 'YG-WEB-007');

it('lets marketing filter newsletter subscribers by state and source', function (): void {
    $marketing = User::factory()->create(['status' => 'active'])->assignRole('marketing');
    $this->actingAs($marketing)->get(route('filament.admin.resources.newsletter-subscribers.index'))->assertOk();

    $active = t208Subscriber(['email' => 'active@example.com', 'source' => 'footer']);
    $gone = t208Subscriber(['email' => 'gone@example.com', 'source' => 'home', 'unsubscribed_at' => now()]);

    Livewire::actingAs($marketing)->test(ListNewsletterSubscribers::class)
        ->filterTable('state', 'subscribed')
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$gone])
        ->resetTableFilters()
        ->filterTable('state', 'unsubscribed')
        ->assertCanSeeTableRecords([$gone])
        ->assertCanNotSeeTableRecords([$active])
        ->resetTableFilters()
        ->filterTable('source', 'footer')
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$gone]);
})->group('T-P2-08', 'A2', 'YG-WEB-008');

it('shows the staff unsubscribe action only for active subscribers and records it as a staff action', function (): void {
    $marketing = User::factory()->create(['status' => 'active'])->assignRole('marketing');
    $active = t208Subscriber(['email' => 'active@example.com']);
    $gone = t208Subscriber(['email' => 'gone@example.com', 'unsubscribed_at' => now()]);

    Livewire::actingAs($marketing)->test(ListNewsletterSubscribers::class)
        ->assertTableActionVisible('unsubscribe', $active)
        ->assertTableActionHidden('unsubscribe', $gone)
        ->callTableAction('unsubscribe', $active)
        ->assertNotified(__('contact.admin.unsubscribed_done'));

    expect($active->fresh()->isSubscribed())->toBeFalse()
        ->and($active->fresh()->unsubscribed_at)->not->toBeNull();

    $audit = Activity::query()->where('event', 'newsletter.unsubscribed')->where('subject_id', $active->id)->sole();
    expect($audit->causer_id)->toBe($marketing->id)
        ->and(data_get($audit->properties, 'context.by'))->toBe('staff');
})->group('T-P2-08', 'A2', 'YG-WEB-008');

it('exports a sanitized subscriber CSV with one lead.exported audit entry and no subscriber addresses in the log', function (): void {
    $marketing = User::factory()->create(['status' => 'active'])->assignRole('marketing');
    t208Subscriber(['email' => '=evil@example.com', 'source' => 'footer']);
    t208Subscriber(['email' => 'plain@example.com', 'source' => 'home', 'unsubscribed_at' => now()]);

    $component = Livewire::actingAs($marketing)->test(ListNewsletterSubscribers::class)
        ->filterTable('state', 'subscribed')
        ->callAction(TestAction::make('exportCsv'))
        ->assertFileDownloaded('newsletter-subscribers.csv');

    $csv = base64_decode($component->effects['download']['content'], true);
    expect(str_starts_with((string) $csv, "\xEF\xBB\xBF"))->toBeTrue()
        ->and($csv)->toContain("'=evil@example.com")
        ->and($csv)->not->toContain('plain@example.com');

    $exports = Activity::query()->where('event', 'lead.exported')->get();
    expect($exports)->toHaveCount(1)
        ->and(json_encode($exports->first()->properties))->toContain('newsletter_subscribers')
        ->not->toContain('evil@example.com')
        ->not->toContain('plain@example.com');
})->group('T-P2-08', 'A2', 'YG-WEB-008');
