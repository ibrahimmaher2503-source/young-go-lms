<?php

// T-P9-03 | YG-GAP-MKT-001 R-021 YG-GAP-ID-002 | A1 A2

declare(strict_types=1);

use App\Domain\Identity\ActingLearner;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\Privacy\MarketingTagIds;
use App\Domain\Settings\Actions\SaveMarketingTags;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

/** Saves both marketing IDs through the real action, as the marketing manager would. */
function tp903ConfigureTags(?string $ga4 = 'G-ABCD1234', ?string $pixel = '1234567890'): void
{
    $manager = User::factory()->create(['status' => 'active'])->assignRole('marketing');
    app(SaveMarketingTags::class)($manager, ['ga4_measurement_id' => $ga4, 'meta_pixel_id' => $pixel]);
}

function tp903CookieValue(TestResponse $response, string $name): ?string
{
    foreach ($response->headers->getCookies() as $cookie) {
        if ($cookie->getName() === $name) {
            return (string) $cookie->getValue();
        }
    }

    return null;
}

/** Accept as a guest and return the encrypted decision cookie value to replay on later requests. */
function tp903AcceptedCookie(TestResponse $response): string
{
    $value = tp903CookieValue($response, config('consent.cookie'));
    expect($value)->not->toBeNull();

    return (string) $value;
}

it('shows the banner and no tag to a guest before any choice on public pages', function (): void {
    tp903ConfigureTags();

    foreach (['/contact', '/courses', '/'] as $url) {
        $html = (string) $this->get($url)->assertOk()->getContent();
        expect($html)->toContain('data-consent-banner')
            ->and($html)->not->toContain('googletagmanager.com')
            ->and($html)->not->toContain('connect.facebook.net')
            ->and($html)->not->toContain('fbq(')
            ->and($html)->not->toContain('gtag(');
    }
})->group('T-P9-03', 'A1');

it('keeps tags off after a decline and shows the banner again for another consent version', function (): void {
    tp903ConfigureTags();

    $declined = tp903CookieValue($this->post(route('consent.store'), ['decision' => 'decline']), config('consent.cookie'));
    $html = (string) $this->withUnencryptedCookie(config('consent.cookie'), (string) $declined)->get('/courses')->assertOk()->getContent();
    expect($html)->not->toContain('googletagmanager.com')
        ->and($html)->not->toContain('connect.facebook.net')
        ->and($html)->not->toContain('data-consent-banner')
        ->and($html)->toContain('data-consent-settings');

    $html = (string) $this->withUnencryptedCookie(config('consent.cookie'), 'cookies-v0-old|accepted')->get('/courses')->assertOk()->getContent();
    expect($html)->toContain('data-consent-banner')
        ->and($html)->not->toContain('googletagmanager.com');
})->group('T-P9-03', 'A1');

it('prints the GA4 and Pixel tags with their configured IDs only after acceptance and hides the banner', function (): void {
    tp903ConfigureTags();
    $accepted = tp903AcceptedCookie($this->post(route('consent.store'), ['decision' => 'accept']));

    $html = (string) $this->withUnencryptedCookie(config('consent.cookie'), $accepted)->get('/contact')->assertOk()->getContent();
    expect($html)->toContain('googletagmanager.com')
        ->and($html)->toContain('G-ABCD1234')
        ->and($html)->toContain('connect.facebook.net')
        ->and($html)->toContain('1234567890')
        ->and($html)->not->toContain('data-consent-banner');
})->group('T-P9-03', 'A1');

it('renders no banner, link or tag when no marketing ID is configured, even with an accepted cookie', function (): void {
    tp903ConfigureTags(null, null);
    $accepted = tp903AcceptedCookie($this->post(route('consent.store'), ['decision' => 'accept']));

    $html = (string) $this->withUnencryptedCookie(config('consent.cookie'), $accepted)->get('/courses')->assertOk()->getContent();
    expect($html)->not->toContain('data-consent-banner')
        ->and($html)->not->toContain('data-consent-settings')
        ->and($html)->not->toContain('googletagmanager.com')
        ->and($html)->not->toContain('connect.facebook.net');
})->group('T-P9-03', 'A1');

it('never renders tags or the banner on auth, learner, error or preview surfaces even when accepted', function (): void {
    tp903ConfigureTags();
    $accepted = tp903AcceptedCookie($this->post(route('consent.store'), ['decision' => 'accept']));

    foreach (['/login', '/register'] as $url) {
        $html = (string) $this->withUnencryptedCookie(config('consent.cookie'), $accepted)->get($url)->assertOk()->getContent();
        expect($html)->not->toContain('googletagmanager.com')
            ->and($html)->not->toContain('connect.facebook.net')
            ->and($html)->not->toContain('data-consent-banner');
    }

    $missing = $this->withUnencryptedCookie(config('consent.cookie'), $accepted)->get('/tp903-no-such-page');
    $missing->assertNotFound();
    expect($missing->getContent())->not->toContain('googletagmanager.com')
        ->and($missing->getContent())->not->toContain('data-consent-banner');

    $learner = User::factory()->create(['status' => 'active', 'email_verified_at' => now()])->assignRole('student');
    $html = (string) $this->actingAs($learner)->withUnencryptedCookie(config('consent.cookie'), $accepted)->get('/my')->assertOk()->getContent();
    expect($html)->not->toContain('googletagmanager.com')
        ->and($html)->not->toContain('data-consent-banner');
})->group('T-P9-03', 'A2');

it('keeps tags and the banner off for a child account and a parent in child context, and refuses their consent post', function (): void {
    tp903ConfigureTags();
    $accepted = tp903AcceptedCookie($this->post(route('consent.store'), ['decision' => 'accept']));

    $parent = User::factory()->create(['status' => 'active', 'email_verified_at' => now()])->assignRole('parent');
    $child = User::factory()->create(['status' => 'active', 'email' => null, 'email_verified_at' => null])->assignRole('student');
    GuardianChildLink::query()->create([
        'guardian_id' => $parent->id,
        'child_id' => $child->id,
        'status' => GuardianChildLink::STATUS_VERIFIED,
        'method' => GuardianChildLink::METHOD_CREATED_BY_PARENT,
        'verified_at' => now(),
    ]);

    $childHtml = (string) $this->actingAs($child)->withUnencryptedCookie(config('consent.cookie'), $accepted)->get('/courses')->assertOk()->getContent();
    expect($childHtml)->not->toContain('googletagmanager.com')
        ->and($childHtml)->not->toContain('data-consent-banner');

    $parentHtml = (string) $this->actingAs($parent)
        ->withSession([ActingLearner::SESSION_KEY => $child->id])
        ->withUnencryptedCookie(config('consent.cookie'), $accepted)
        ->get('/courses')->assertOk()->getContent();
    expect($parentHtml)->not->toContain('googletagmanager.com')
        ->and($parentHtml)->not->toContain('data-consent-banner');

    $refused = $this->actingAs($child)->post(route('consent.store'), ['decision' => 'accept']);
    $refused->assertForbidden();
    expect(tp903CookieValue($refused, config('consent.cookie')))->toBeNull()
        ->and(DB::table('consents')->where('user_id', $child->id)->count())->toBe(0);
})->group('T-P9-03', 'A2');

it('records one consent row per signed-in adult decision and closes it on decline or version bump', function (): void {
    $adult = User::factory()->create(['status' => 'active', 'email_verified_at' => now()])->assignRole('student');
    $other = User::factory()->create(['status' => 'active', 'email_verified_at' => now()])->assignRole('student');
    DB::table('consents')->insert(['user_id' => $other->id, 'type' => 'cookies', 'version' => 'cookies-v1-draft', 'granted_at' => now()]);

    $this->actingAs($adult)->post(route('consent.store'), ['decision' => 'accept'])->assertRedirect();
    $this->actingAs($adult)->post(route('consent.store'), ['decision' => 'accept'])->assertRedirect();

    $rows = DB::table('consents')->where('user_id', $adult->id)->where('type', 'cookies')->get();
    expect($rows)->toHaveCount(1)
        ->and($rows->first()->version)->toBe('cookies-v1-draft')
        ->and($rows->first()->granted_at)->not->toBeNull()
        ->and($rows->first()->withdrawn_at)->toBeNull();

    $this->actingAs($adult)->post(route('consent.store'), ['decision' => 'decline'])->assertRedirect();
    expect(DB::table('consents')->where('user_id', $adult->id)->whereNull('withdrawn_at')->count())->toBe(0);

    $this->actingAs($adult)->post(route('consent.store'), ['decision' => 'accept'])->assertRedirect();
    config(['consent.version' => 'cookies-v2']);
    $this->actingAs($adult)->post(route('consent.store'), ['decision' => 'accept'])->assertRedirect();

    $active = DB::table('consents')->where('user_id', $adult->id)->whereNull('withdrawn_at')->get();
    expect($active)->toHaveCount(1)
        ->and($active->first()->version)->toBe('cookies-v2')
        ->and(DB::table('consents')->where('user_id', $other->id)->whereNull('withdrawn_at')->count())->toBe(1);
})->group('T-P9-03', 'A1');

it('stores no consent row for guests and sets an httpOnly SameSite=Lax cookie of about 180 days', function (): void {
    $response = $this->post(route('consent.store'), ['decision' => 'accept']);

    expect(DB::table('consents')->count())->toBe(0);

    $cookie = collect($response->headers->getCookies())->first(fn ($c): bool => $c->getName() === config('consent.cookie'));
    expect($cookie)->not->toBeNull()
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and(strtolower((string) $cookie->getSameSite()))->toBe('lax')
        ->and($cookie->getExpiresTime())->toBeGreaterThan(now()->addDays(179)->timestamp)
        ->and($cookie->getExpiresTime())->toBeLessThan(now()->addDays(181)->timestamp);
})->group('T-P9-03', 'A1');

it('expires tag cookies on decline, including an _ga_ prefixed cookie the browser sent', function (): void {
    tp903ConfigureTags();
    $accepted = tp903AcceptedCookie($this->post(route('consent.store'), ['decision' => 'accept']));

    $response = $this->withUnencryptedCookie(config('consent.cookie'), $accepted)
        ->withUnencryptedCookie('_ga_ABC123', '1')
        ->post(route('consent.store'), ['decision' => 'decline']);

    $expired = collect($response->headers->getCookies())
        ->filter(fn ($c): bool => $c->getExpiresTime() > 0 && $c->getExpiresTime() <= time())
        ->map(fn ($c): string => $c->getName())
        ->unique()
        ->values()
        ->all();

    expect($expired)->toContain('_ga', '_fbp', '_fbc', '_ga_ABC123');
    $decided = Crypt::decryptString((string) tp903CookieValue($response, config('consent.cookie')));
    expect($decided)->toEndWith('|declined');
})->group('T-P9-03', 'A1');

it('shows the matching consent state on the preference page and hides the buttons when no IDs are configured', function (): void {
    tp903ConfigureTags();

    $this->get(route('consent.show'))->assertOk()->assertSee('data-consent-state="none"', false);

    $accepted = tp903AcceptedCookie($this->post(route('consent.store'), ['decision' => 'accept']));
    $this->withUnencryptedCookie(config('consent.cookie'), $accepted)->get(route('consent.show'))
        ->assertOk()->assertSee('data-consent-state="accepted"', false);

    $declined = tp903CookieValue($this->post(route('consent.store'), ['decision' => 'decline']), config('consent.cookie'));
    $this->withUnencryptedCookie(config('consent.cookie'), (string) $declined)->get(route('consent.show'))
        ->assertOk()->assertSee('data-consent-state="declined"', false);

    tp903ConfigureTags(null, null);
    $html = (string) $this->get(route('consent.show'))->assertOk()->getContent();
    expect($html)->not->toContain('data-consent="accept"')
        ->and($html)->not->toContain('data-consent="decline"');
})->group('T-P9-03', 'A1');

it('redirects a plain form post back to the same host and to the preference page for a foreign referer', function (): void {
    $this->from('/courses')->post(route('consent.store'), ['decision' => 'accept'])->assertRedirect('/courses');

    $this->from('https://evil.example.com/page')->post(route('consent.store'), ['decision' => 'accept'])
        ->assertRedirect(route('consent.show'));
})->group('T-P9-03', 'A1');

it('refuses a missing or unknown decision and sets no consent cookie', function (): void {
    $this->post(route('consent.store'), [])->assertSessionHasErrors('decision');

    $unknown = $this->post(route('consent.store'), ['decision' => 'maybe']);
    $unknown->assertSessionHasErrors('decision');
    expect(tp903CookieValue($unknown, config('consent.cookie')))->toBeNull();
})->group('T-P9-03', 'A1');

it('validates marketing IDs, refuses unauthorized and inactive users and audits only real changes', function (): void {
    $student = User::factory()->create(['status' => 'active'])->assignRole('student');
    $finance = User::factory()->create(['status' => 'active'])->assignRole('finance');
    $inactive = User::factory()->create(['status' => 'suspended'])->assignRole('marketing');
    $manager = User::factory()->create(['status' => 'active'])->assignRole('marketing');
    $save = app(SaveMarketingTags::class);

    expect(fn () => $save($student, ['ga4_measurement_id' => 'G-ABCD1234']))->toThrow(AuthorizationException::class)
        ->and(fn () => $save($finance, ['ga4_measurement_id' => 'G-ABCD1234']))->toThrow(AuthorizationException::class)
        ->and(fn () => $save($inactive, ['ga4_measurement_id' => 'G-ABCD1234']))->toThrow(AuthorizationException::class);

    foreach (['UA-123', 'G-abc', '"><script>'] as $bad) {
        expect(fn () => $save($manager, ['ga4_measurement_id' => $bad]))->toThrow(ValidationException::class);
    }
    foreach (['abc12345', '12345'] as $bad) {
        expect(fn () => $save($manager, ['meta_pixel_id' => $bad]))->toThrow(ValidationException::class);
    }

    $save($manager, ['ga4_measurement_id' => 'G-ABCD1234', 'meta_pixel_id' => '1234567890']);
    expect(Activity::query()->where('event', 'marketing.tags_saved')->count())->toBe(1);

    $save($manager, ['ga4_measurement_id' => 'G-ABCD1234', 'meta_pixel_id' => '1234567890']);
    expect(Activity::query()->where('event', 'marketing.tags_saved')->count())->toBe(1);

    $save($manager, ['ga4_measurement_id' => '', 'meta_pixel_id' => null]);
    expect(Activity::query()->where('event', 'marketing.tags_saved')->count())->toBe(2);
})->group('T-P9-03', 'A1');

it('never prints an invalid ID forced into settings and keeps the shape rules', function (): void {
    DB::table('settings')->where('group', 'marketing')->where('name', 'ga4_measurement_id')->update(['payload' => json_encode('"><script>alert(1)</script>')]);
    DB::table('settings')->where('group', 'marketing')->where('name', 'meta_pixel_id')->update(['payload' => json_encode('12345')]);

    expect(MarketingTagIds::ga4('"><script>'))->toBeNull()
        ->and(MarketingTagIds::metaPixel('12345'))->toBeNull()
        ->and(MarketingTagIds::ga4('G-ABCD1234'))->toBe('G-ABCD1234')
        ->and(MarketingTagIds::metaPixel('1234567890'))->toBe('1234567890');

    $accepted = tp903AcceptedCookie($this->post(route('consent.store'), ['decision' => 'accept']));
    $html = (string) $this->withUnencryptedCookie(config('consent.cookie'), $accepted)->get('/courses')->assertOk()->getContent();
    expect($html)->not->toContain('<script>alert(1)</script>')
        ->and($html)->not->toContain('connect.facebook.net');
})->group('T-P9-03', 'A1');
