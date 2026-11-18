<?php

// T-P9-09 | PRD 19.2, 22 | A1 A2

declare(strict_types=1);

use App\Domain\Audit\AuditLogger;
use App\Domain\Settings\Actions\SaveGeneralSettings;
use App\Domain\Settings\Actions\SaveManualPaymentPayee;
use App\Domain\Settings\GeneralSettings;
use App\Domain\Settings\PaymentsSettings;
use App\Domain\Settings\Queries\ManualPaymentPayee;
use App\Filament\Pages\GeneralSettingsPage;
use App\Filament\Pages\PaymentPayeeSettings;
use App\Filament\Pages\SettingsHub;
use App\Filament\Pages\WorkspaceDashboard;
use App\Filament\Resources\Courses\CourseResource;
use App\Filament\Resources\LedgerEntries\LedgerEntryResource;
use App\Filament\Resources\Plans\PlanResource;
use App\Filament\Resources\StaffUsers\StaffUserResource;
use App\Filament\Shared\StaffNavigation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
    app()->setLocale('en');
});

function tp909Staff(string $role): User
{
    return User::factory()->create(['status' => 'active', 'locale' => 'en'])->assignRole($role);
}

function tp909GeneralInput(array $overrides = []): array
{
    return array_merge([
        'default_locale' => 'en',
        'default_market' => 'EG',
        'site_name_ar' => 'يونغ جو',
        'site_name_en' => 'Young Go',
        'tagline_ar' => null,
        'tagline_en' => 'Creative courses',
        'contact_email' => 'hello@young-go.example',
        'contact_phone' => '+20 100 000 0000',
        'contact_address_ar' => null,
        'contact_address_en' => 'Cairo',
        'social_facebook' => 'https://facebook.com/younggo',
        'social_instagram' => null,
        'social_youtube' => null,
        'social_tiktok' => null,
        'social_x' => null,
        'social_linkedin' => null,
        'banner_enabled' => false,
        'banner_text_ar' => null,
        'banner_text_en' => null,
        'mail_from_name' => null,
        'mail_from_address' => null,
        'mail_reply_to' => null,
    ], $overrides);
}

function tp909AuditCount(string $event): int
{
    return Activity::query()->where('event', $event)->count();
}

/**
 * Sidebar as the panel shows it for this user: group label => item labels, in order.
 *
 * @return array<string, list<string>>
 */
function tp909Sidebar(User $user, string $panelId): array
{
    Filament::setCurrentPanel(Filament::getPanel($panelId));
    test()->actingAs($user);

    $out = [];
    foreach (Filament::getNavigation() as $group) {
        $label = $group->getLabel();
        $out[$label] = array_map(static fn ($item): string => (string) $item->getLabel(), $group->getItems());
    }

    return $out;
}

// --- A1: navigation parity -------------------------------------------------------------------

it('lists every registered class of each staff panel in exactly one catalog group, so More is empty', function (): void {
    foreach (['admin', 'finance', 'instructor'] as $panelId) {
        $panel = Filament::getPanel($panelId);
        $registered = [...$panel->getPages(), ...$panel->getResources()];
        $unplaced = [];
        foreach ($registered as $class) {
            if ($class === WorkspaceDashboard::class) {
                continue;
            }
            $occurrences = 0;
            foreach (StaffNavigation::GROUPS as $classes) {
                $occurrences += count(array_filter($classes, static fn (string $c): bool => $c === $class));
            }
            if ($occurrences !== 1) {
                $unplaced[] = $class.' x'.$occurrences;
            }
        }

        expect($unplaced)->toBe([], "Panel {$panelId} has classes outside the catalog or in two groups");
    }
})->group('T-P9-09', 'A1');

it('orders the sidebar groups as the catalog lists them and drops empty groups', function (): void {
    $sidebar = tp909Sidebar(tp909Staff('admin'), 'admin');
    $expected = ['courses', 'site', 'sales', 'finance', 'people', 'inbox', 'reports', 'ai', 'settings'];
    $labels = array_map(static fn (string $key): string => (string) __('navigation.groups.'.$key), $expected);

    $present = array_values(array_filter($labels, static fn (string $label): bool => array_key_exists($label, $sidebar)));
    // The Dashboard item sits ungrouped (empty label) above the groups.
    $grouped = array_values(array_filter(array_keys($sidebar), static fn (string $label): bool => $label !== ''));
    expect($grouped)->toBe($present)
        ->and(array_keys($sidebar))->not->toContain((string) __('navigation.groups.more'));
})->group('T-P9-09', 'A1');

it('shows the course and plan items under their groups for the admin, and the ledger only in the finance panel', function (): void {
    $admin = tp909Staff('admin');
    $adminSidebar = tp909Sidebar($admin, 'admin');

    expect($adminSidebar[(string) __('navigation.groups.courses')] ?? [])->toContain(CourseResource::getNavigationLabel())
        ->and($adminSidebar[(string) __('navigation.groups.sales')] ?? [])->toContain(PlanResource::getNavigationLabel())
        ->and($adminSidebar[(string) __('navigation.groups.people')] ?? [])->toContain(StaffUserResource::getNavigationLabel());

    $finance = tp909Staff('finance');
    $financeSidebar = tp909Sidebar($finance, 'finance');
    $allFinanceItems = array_merge(...array_values($financeSidebar));

    expect($allFinanceItems)->toContain(LedgerEntryResource::getNavigationLabel())
        ->and($allFinanceItems)->not->toContain(CourseResource::getNavigationLabel());
})->group('T-P9-09', 'A1');

it('hides items from users without the permission and never shows the instructor panel finance classes to support', function (): void {
    $support = tp909Staff('support');
    $adminItems = array_merge(...array_values(tp909Sidebar($support, 'admin')));

    // Support holds user.view (Users shows), but no sales permission (Plans stay hidden).
    expect($adminItems)->toContain(StaffUserResource::getNavigationLabel())
        ->and($adminItems)->not->toContain(PlanResource::getNavigationLabel());

    $marketingItems = array_merge(...array_values(tp909Sidebar(tp909Staff('marketing'), 'admin')));
    expect($marketingItems)->not->toContain(StaffUserResource::getNavigationLabel());

    $instructor = tp909Staff('instructor_academy');
    $instructorItems = array_merge(...array_values(tp909Sidebar($instructor, 'instructor')));
    expect($instructorItems)->not->toContain(LedgerEntryResource::getNavigationLabel())
        ->and($instructorItems)->not->toContain(PlanResource::getNavigationLabel());
})->group('T-P9-09', 'A1');

// --- A2: secrets are never displayed -----------------------------------------------------------

it('never renders the application key or a secret in the settings pages or the hub', function (): void {
    $super = tp909Staff('super_admin');
    $this->actingAs($super);
    $key = (string) config('app.key');

    foreach (['/admin/settings', '/admin/general-settings', '/admin/payment-payee'] as $url) {
        $html = $this->get($url)->assertOk()->getContent();
        expect($key)->not->toBe('');
        expect(str_contains($html, $key))->toBeFalse("{$url} renders the app key");
    }
})->group('T-P9-09', 'A2');

it('records the payee audit with a flag only and never the payee text', function (): void {
    $admin = tp909Staff('super_admin');
    app(SaveManualPaymentPayee::class)($admin, ['manual_payee' => 'Account holder: Secret Name 0100-SECRET']);

    $row = Activity::query()->where('event', 'settings.payee_saved')->firstOrFail();
    $json = json_encode($row->properties);

    expect($json)->not->toContain('Secret Name')
        ->and($json)->not->toContain('0100-SECRET')
        ->and(data_get($row->properties, 'attributes.payee_set', data_get($row->properties, 'after.payee_set')))->toBeTrue();
})->group('T-P9-09', 'A2');

// --- Save general settings -------------------------------------------------------------------

it('saves and reloads every general field and writes one audit row with before and after, and a second identical save writes none', function (): void {
    $super = tp909Staff('super_admin');
    app(SaveGeneralSettings::class)($super, tp909GeneralInput(['banner_enabled' => true, 'banner_text_en' => 'Enrol before Friday']));

    $settings = app(GeneralSettings::class)->refresh();
    expect($settings->site_name_en)->toBe('Young Go')
        ->and($settings->contact_email)->toBe('hello@young-go.example')
        ->and($settings->social_facebook)->toBe('https://facebook.com/younggo')
        ->and($settings->banner_enabled)->toBeTrue()
        ->and($settings->banner_text_en)->toBe('Enrol before Friday')
        ->and(tp909AuditCount('settings.general_saved'))->toBe(1);

    app(SaveGeneralSettings::class)($super, tp909GeneralInput(['banner_enabled' => true, 'banner_text_en' => 'Enrol before Friday']));
    expect(tp909AuditCount('settings.general_saved'))->toBe(1);
})->group('T-P9-09', 'A1');

it('refuses invalid email, javascript urls, short phone, market SA, locale fr and a name with a newline', function (array $overrides): void {
    $super = tp909Staff('super_admin');
    expect(fn () => app(SaveGeneralSettings::class)($super, tp909GeneralInput($overrides)))
        ->toThrow(ValidationException::class);
    expect(tp909AuditCount('settings.general_saved'))->toBe(0);
})->with([
    'bad email' => [['contact_email' => 'not-an-email']],
    'javascript url' => [['social_x' => 'javascript:alert(1)']],
    'short phone' => [['contact_phone' => '12']],
    'market SA' => [['default_market' => 'SA']],
    'locale fr' => [['default_locale' => 'fr']],
    'newline in name' => [['mail_from_name' => "Young\nGo"]],
])->group('T-P9-09', 'A1');

it('refuses the save for admin, finance and marketing, and an inactive super admin', function (): void {
    foreach (['admin', 'finance', 'marketing'] as $role) {
        expect(fn () => app(SaveGeneralSettings::class)(tp909Staff($role), tp909GeneralInput()))
            ->toThrow(AuthorizationException::class);
    }

    $inactive = User::factory()->create(['status' => 'suspended'])->assignRole('super_admin');
    expect(fn () => app(SaveGeneralSettings::class)($inactive, tp909GeneralInput()))->toThrow(AuthorizationException::class);
})->group('T-P9-09', 'A1');

it('rolls back the settings when the audit cannot be written', function (): void {
    $super = tp909Staff('super_admin');
    // AuditLogger is final, so the audit write fails through Spatie: disabled logging makes record() return null.
    config(['activitylog.enabled' => false]);

    expect(fn () => app(SaveGeneralSettings::class)($super, tp909GeneralInput(['site_name_en' => 'Rolled Back'])))
        ->toThrow(RuntimeException::class);

    expect(app(GeneralSettings::class)->refresh()->site_name_en)->toBeNull();
})->group('T-P9-09', 'A1');

// --- Payee precedence and checkout --------------------------------------------------------------

it('resolves the payee as settings first, then the environment value, then null, with a blank string counting as unset', function (): void {
    config(['manual_payments.payee' => 'Env Payee Name']);
    $super = tp909Staff('super_admin');
    $query = app(ManualPaymentPayee::class);

    expect($query())->toBe('Env Payee Name');

    app(SaveManualPaymentPayee::class)($super, ['manual_payee' => 'Settings Payee']);
    expect($query())->toBe('Settings Payee');

    app(SaveManualPaymentPayee::class)($super, ['manual_payee' => '   ']);
    expect(app(PaymentsSettings::class)->refresh()->manual_payee)->toBeNull()
        ->and($query())->toBe('Env Payee Name');

    config(['manual_payments.payee' => null]);
    expect($query())->toBeNull();
})->group('T-P9-09', 'A1');

it('refuses the payee save for finance and a payee over 500 characters', function (): void {
    expect(fn () => app(SaveManualPaymentPayee::class)(tp909Staff('finance'), ['manual_payee' => 'Name']))
        ->toThrow(AuthorizationException::class);

    expect(fn () => app(SaveManualPaymentPayee::class)(tp909Staff('super_admin'), ['manual_payee' => str_repeat('x', 501)]))
        ->toThrow(ValidationException::class);
})->group('T-P9-09', 'A1');

// --- Mail sender identity --------------------------------------------------------------------

it('applies the From and Reply-To settings to outgoing mail and leaves the config when they are empty', function (): void {
    config(['mail.default' => 'array', 'mail.from.address' => 'config@young-go.example', 'mail.from.name' => 'Config Name']);
    $captured = null;
    Event::listen(MessageSending::class, function (MessageSending $event) use (&$captured): void {
        $captured = $event->message;
    });

    Mail::mailer('array')->raw('Body', function ($message): void {
        $message->to('learner@example.test')->subject('Empty settings');
    });
    expect($captured->getFrom()[0]->getAddress())->toBe('config@young-go.example')
        ->and($captured->getReplyTo())->toBe([]);

    app(SaveGeneralSettings::class)(tp909Staff('super_admin'), tp909GeneralInput([
        'mail_from_name' => 'Young Go Team', 'mail_from_address' => 'team@young-go.example', 'mail_reply_to' => 'help@young-go.example',
    ]));

    Mail::mailer('array')->raw('Body', function ($message): void {
        $message->to('learner@example.test')->subject('Set settings');
    });
    expect($captured->getFrom()[0]->getAddress())->toBe('team@young-go.example')
        ->and($captured->getFrom()[0]->getName())->toBe('Young Go Team')
        ->and($captured->getReplyTo()[0]->getAddress())->toBe('help@young-go.example');
})->group('T-P9-09', 'A1');

// --- Public banner ----------------------------------------------------------------------------

it('shows the banner on a public page only when enabled with text, and escapes the text', function (): void {
    $super = tp909Staff('super_admin');
    app(SaveGeneralSettings::class)($super, tp909GeneralInput(['banner_enabled' => false, 'banner_text_en' => 'Hidden text']));
    $this->get('/')->assertOk()->assertDontSee('data-site-banner', false);

    app(SaveGeneralSettings::class)($super, tp909GeneralInput(['banner_enabled' => true, 'banner_text_en' => '<b>Sale</b> now']));
    $html = $this->get('/')->assertOk()->getContent();
    expect($html)->toContain('data-site-banner')
        ->and($html)->toContain('&lt;b&gt;Sale&lt;/b&gt; now')
        ->and($html)->not->toContain('<b>Sale</b>');

    app(SaveGeneralSettings::class)($super, tp909GeneralInput(['banner_enabled' => true, 'banner_text_en' => null, 'banner_text_ar' => null]));
    $this->get('/')->assertOk()->assertDontSee('data-site-banner', false);
})->group('T-P9-09', 'A1');

// --- Hub --------------------------------------------------------------------------------------

it('shows super admin all nine hub cards, admin only the cards it may open, and no card for a student', function (): void {
    $this->actingAs(tp909Staff('super_admin'));
    $html = $this->get('/admin/settings')->assertOk()->getContent();
    foreach (['general', 'payee', 'notifications', 'marketing', 'privacy', 'data_requests', 'ai', 'roles', 'audit'] as $key) {
        expect($html)->toContain('data-settings-entry="'.$key.'"');
    }

    $this->actingAs(tp909Staff('admin'));
    $adminHtml = $this->get('/admin/settings')->assertOk()->getContent();
    expect($adminHtml)->not->toContain('data-settings-entry="general"')
        ->and($adminHtml)->not->toContain('data-settings-entry="payee"');

    $this->actingAs(tp909Staff('marketing'));
    $marketing = $this->get('/admin/settings')->getContent();
    expect($marketing)->not->toContain('data-settings-entry="general"');

    $this->actingAs(User::factory()->create(['status' => 'active'])->assignRole('student'));
    $this->get('/admin/settings')->assertForbidden();
})->group('T-P9-09', 'A1');

it('lets only super admin open the general and payee settings pages', function (): void {
    $this->actingAs(tp909Staff('super_admin'));
    $this->get('/admin/general-settings')->assertOk();
    $this->get('/admin/payment-payee')->assertOk();

    $this->actingAs(tp909Staff('admin'));
    $this->get('/admin/general-settings')->assertForbidden();
    $this->get('/admin/payment-payee')->assertForbidden();

    expect(GeneralSettingsPage::canAccess())->toBeFalse()
        ->and(PaymentPayeeSettings::canAccess())->toBeFalse()
        ->and(SettingsHub::canAccess())->toBeTrue();
})->group('T-P9-09', 'A1');
