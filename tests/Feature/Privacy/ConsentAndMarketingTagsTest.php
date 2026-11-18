<?php

declare(strict_types=1);

use App\Domain\Certificates\Certificate;
use App\Domain\Completion\Actions\SaveCompletionRules;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Identity\ActingLearner;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\Privacy\MarketingTagIds;
use App\Domain\Progress\Actions\RecordProgress;
use App\Domain\Settings\Actions\SaveMarketingTags;
use App\Filament\Pages\MarketingTags;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

function cmtExtractConsentCookie(array $cookies): string
{
    foreach ($cookies as $cookie) {
        if ($cookie->getName() === config('consent.cookie')) {
            return (string) $cookie->getValue();
        }
    }

    throw new RuntimeException('Consent cookie not returned.');
}

it('gates marketing tag settings, blocks scripts before consent, and handles version bumps and cookie revocation', function (): void {
    $student = User::factory()->create(['status' => 'active'])->assignRole('student');
    $finance = User::factory()->create(['status' => 'active'])->assignRole('finance');
    $marketing = User::factory()->create(['status' => 'active'])->assignRole('marketing');

    // Authorization and ID validation
    $saveTags = app(SaveMarketingTags::class);
    expect(fn () => $saveTags($student, ['ga4_measurement_id' => 'G-ABCD1234']))->toThrow(AuthorizationException::class)
        ->and(fn () => $saveTags($finance, ['ga4_measurement_id' => 'G-ABCD1234']))->toThrow(AuthorizationException::class)
        ->and(fn () => $saveTags($marketing, ['ga4_measurement_id' => 'UA-12345']))->toThrow(ValidationException::class)
        ->and(fn () => $saveTags($marketing, ['ga4_measurement_id' => '"><script>']))->toThrow(ValidationException::class)
        ->and(fn () => $saveTags($marketing, ['meta_pixel_id' => '12345']))->toThrow(ValidationException::class)
        ->and(MarketingTagIds::ga4('"><script>'))->toBeNull()
        ->and(MarketingTagIds::metaPixel('abc123'))->toBeNull();

    // Filament admin page authorization + edit action
    Filament::setCurrentPanel('admin');
    $this->actingAs($student);
    Livewire::test(MarketingTags::class)->assertForbidden();

    $this->actingAs($marketing);
    Livewire::test(MarketingTags::class)
        ->assertOk()
        ->callAction('edit', data: ['ga4_measurement_id' => 'G-ABCD1234', 'meta_pixel_id' => '1234567890']);

    expect(Activity::query()->where('event', 'marketing.tags_saved')->count())->toBe(1);
    $saveTags($marketing, ['ga4_measurement_id' => 'G-ABCD1234', 'meta_pixel_id' => '1234567890']); // unchanged = no extra audit
    expect(Activity::query()->where('event', 'marketing.tags_saved')->count())->toBe(1);

    // Guest before consent sees banner, NO tags
    auth()->logout();
    foreach (['/courses', '/contact'] as $url) {
        $html = $this->get($url)->assertOk()->getContent();
        expect($html)->toContain('data-consent-banner')
            ->not->toContain('googletagmanager.com')
            ->not->toContain('connect.facebook.net')
            ->not->toContain('fbq(')
            ->not->toContain('gtag(');
    }

    // Invalid decision rejected
    $this->post(route('consent.store'), ['decision' => 'maybe'])->assertSessionHasErrors('decision');

    // Accepting sets httpOnly SameSite=Lax cookie and prints tags without banner
    $acceptRes = $this->from('/courses')->post(route('consent.store'), ['decision' => 'accept']);
    $acceptRes->assertRedirect('/courses');
    $acceptedCookie = cmtExtractConsentCookie($acceptRes->headers->getCookies());
    $cookieObj = collect($acceptRes->headers->getCookies())->first(fn ($c): bool => $c->getName() === config('consent.cookie'));
    expect($cookieObj)->not->toBeNull()
        ->and($cookieObj->isHttpOnly())->toBeTrue()
        ->and(strtolower((string) $cookieObj->getSameSite()))->toBe('lax')
        ->and(DB::table('consents')->count())->toBe(0); // guest stores no DB row

    $withTags = $this->withUnencryptedCookie(config('consent.cookie'), $acceptedCookie)->get('/courses')->assertOk()->getContent();
    expect($withTags)->toContain('https://www.googletagmanager.com/gtag/js?id=G-ABCD1234')
        ->toContain('1234567890')
        ->not->toContain('data-consent-banner');

    // Version bump invalidates older accepted cookie
    Config::set('consent.version', 'cookies-v2-published');
    $afterBump = $this->withUnencryptedCookie(config('consent.cookie'), $acceptedCookie)->get('/courses')->assertOk()->getContent();
    expect($afterBump)->toContain('data-consent-banner')->not->toContain('googletagmanager.com');
    Config::set('consent.version', 'cookies-v1-draft');

    // Decline expires tag cookies (_ga, _fbp, _fbc, _ga_*) and honors safe external-referer fallback
    $declineRes = $this->withUnencryptedCookie(config('consent.cookie'), $acceptedCookie)
        ->withUnencryptedCookie('_ga_TEST1', '1')
        ->withHeaders(['Referer' => 'https://evil.example/bounce'])
        ->post(route('consent.store'), ['decision' => 'decline']);
    $declineRes->assertRedirect(route('consent.show'));
    $expiredNames = collect($declineRes->headers->getCookies())
        ->filter(fn ($c): bool => $c->getExpiresTime() > 0 && $c->getExpiresTime() < time())
        ->map(fn ($c): string => $c->getName())->unique()->all();
    expect($expiredNames)->toContain('_ga', '_fbp', '_fbc', '_ga_TEST1');

    $declinedCookie = cmtExtractConsentCookie($declineRes->headers->getCookies());
    $this->withUnencryptedCookie(config('consent.cookie'), $declinedCookie)->get(route('consent.show'))
        ->assertOk()->assertSee('data-consent-state="declined"', false);

    // Signed-in adult records active & withdrawn rows in consents table
    $adult = User::factory()->create(['status' => 'active', 'birth_year' => 1990])->assignRole('student');
    $this->actingAs($adult)->postJson(route('consent.store'), ['decision' => 'accept'])->assertNoContent();
    $this->actingAs($adult)->postJson(route('consent.store'), ['decision' => 'accept'])->assertNoContent();
    expect(DB::table('consents')->where('user_id', $adult->id)->where('type', 'cookies')->whereNull('withdrawn_at')->count())->toBe(1);

    $this->actingAs($adult)->postJson(route('consent.store'), ['decision' => 'decline'])->assertNoContent();
    expect(DB::table('consents')->where('user_id', $adult->id)->where('type', 'cookies')->whereNull('withdrawn_at')->count())->toBe(0)
        ->and(DB::table('consents')->where('user_id', $adult->id)->whereNotNull('withdrawn_at')->count())->toBe(1);

    // Clearing all tags suppresses banner/settings even with accepted cookie
    $saveTags($marketing, ['ga4_measurement_id' => null, 'meta_pixel_id' => null]);
    $noTagHtml = $this->withUnencryptedCookie(config('consent.cookie'), $acceptedCookie)->get('/courses')->assertOk()->getContent();
    expect($noTagHtml)->not->toContain('data-consent-banner')->not->toContain('data-consent-settings')->not->toContain('googletagmanager.com');
    $this->get(route('consent.show'))->assertOk()->assertSee(__('consent.none_used'));
})->group('T-P9-03', 'A1', 'YG-GAP-MKT-001');

it('never renders marketing tags on player, auth, error, verification, child, or parent-context surfaces', function (): void {
    $marketing = User::factory()->create(['status' => 'active'])->assignRole('marketing');
    app(SaveMarketingTags::class)($marketing, ['ga4_measurement_id' => 'G-SAFE9999', 'meta_pixel_id' => '9876543210']);

    $acceptRes = $this->postJson(route('consent.store'), ['decision' => 'accept'])->assertNoContent();
    $acceptedCookie = cmtExtractConsentCookie($acceptRes->headers->getCookies());

    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $adult = User::factory()->create(['status' => 'active', 'birth_year' => 1990])->assignRole('student');
    $parent = User::factory()->create(['status' => 'active'])->assignRole('parent');
    $child = User::factory()->create(['status' => 'active', 'email' => null, 'email_verified_at' => null, 'birth_year' => now()->year - 10])->assignRole('student');
    GuardianChildLink::query()->create([
        'guardian_id' => $parent->id,
        'child_id' => $child->id,
        'status' => GuardianChildLink::STATUS_VERIFIED,
        'method' => GuardianChildLink::METHOD_CREATED_BY_PARENT,
        'verified_at' => now(),
    ]);

    $course = Course::query()->create([
        'title' => ['en' => 'Safe Canvas Course', 'ar' => 'دورة الرسم'],
        'slug_ar' => 'cmt-ar-'.uniqid(),
        'slug_en' => 'cmt-en-'.uniqid(),
        'status' => 'published',
        'access_mode' => Course::ACCESS_FREE,
    ]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'Unit 1', 'ar' => 'الوحدة 1']]);
    $section->update(['published' => true]);
    $item = app(ManageLessons::class)->add($instructor, $section, [
        'type' => 'text',
        'title' => ['en' => 'Preview Lesson', 'ar' => 'درس معاينة'],
        'is_preview' => true,
    ]);
    app(SetCurriculumStatus::class)($instructor, $course, [$item->id], CurriculumItem::STATUS_PUBLISHED);
    app(GrantEntitlement::class)($adult, $course, EntitlementSource::Free);
    app(SaveCompletionRules::class)($instructor, $course, ['lesson_percent' => 100]);
    app(RecordProgress::class)->markComplete($adult, $item->fresh());
    $cert = Certificate::query()->where('course_id', $course->id)->where('learner_id', $adult->id)->firstOrFail();

    $assertNoMarketing = static function (string $html): void {
        expect($html)->not->toContain('googletagmanager.com')
            ->not->toContain('connect.facebook.net')
            ->not->toContain('fbq(')
            ->not->toContain('gtag(')
            ->not->toContain('data-consent-banner');
    };

    // Guest auth, verify, minimal error, and public preview routes
    $assertNoMarketing($this->withUnencryptedCookie(config('consent.cookie'), $acceptedCookie)->get(route('login.page'))->assertOk()->getContent());
    $assertNoMarketing($this->withUnencryptedCookie(config('consent.cookie'), $acceptedCookie)->get(route('register.page'))->assertOk()->getContent());
    $assertNoMarketing($this->withUnencryptedCookie(config('consent.cookie'), $acceptedCookie)->get(route('certificates.verify', ['token' => $cert->token]))->assertOk()->getContent());
    $assertNoMarketing($this->withUnencryptedCookie(config('consent.cookie'), $acceptedCookie)->get('/non-existent-route-xyz')->assertNotFound()->getContent());
    $assertNoMarketing($this->withUnencryptedCookie(config('consent.cookie'), $acceptedCookie)->get(route('catalog.preview', ['course' => $course->id, 'item' => $item->id]))->assertOk()->getContent());

    // Entitled adult on learner dashboard, courses, player
    foreach ([route('learner.dashboard'), route('learner.courses'), route('learn.show', ['course' => $course->id, 'item' => $item->id])] as $url) {
        $html = $this->actingAs($adult)->withUnencryptedCookie(config('consent.cookie'), $acceptedCookie)->get($url)->assertOk()->getContent();
        $assertNoMarketing($html);
    }

    // Parent dashboards and parent acting in child context on /courses
    foreach ([route('parent.dashboard'), route('family')] as $url) {
        $html = $this->actingAs($parent)->withUnencryptedCookie(config('consent.cookie'), $acceptedCookie)->get($url)->assertOk()->getContent();
        $assertNoMarketing($html);
    }

    $assertNoMarketing(
        $this->actingAs($parent)
            ->withSession([ActingLearner::SESSION_KEY => $child->id])
            ->withUnencryptedCookie(config('consent.cookie'), $acceptedCookie)
            ->get('/courses')
            ->assertOk()
            ->getContent()
    );
    $this->actingAs($parent)
        ->withSession([ActingLearner::SESSION_KEY => $child->id])
        ->post(route('consent.store'), ['decision' => 'accept'])
        ->assertForbidden();

    // Linked child account browsing /courses and posting to /cookies
    $assertNoMarketing($this->actingAs($child)->withUnencryptedCookie(config('consent.cookie'), $acceptedCookie)->get('/courses')->assertOk()->getContent());
    $this->actingAs($child)->post(route('consent.store'), ['decision' => 'accept'])->assertForbidden();
})->group('T-P9-03', 'A2', 'YG-GAP-MKT-001');
