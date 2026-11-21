<?php

// T-P2-04 | YG-WEB-003, YG-GAP-SRCH-001 | A2

declare(strict_types=1);

use App\Domain\Catalog\CourseStatus;
use App\Domain\Cohorts\Cohort;
use App\Domain\Cohorts\CohortMember;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Entitlements\Entitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    config(['markets.EG.active' => true]);
});

function t204Course(string $slug, array $fields = []): Course
{
    return Course::query()->create([
        'title' => ['ar' => 'ورشة '.$slug, 'en' => ucfirst($slug)],
        'slug_ar' => 'دورة-'.$slug, 'slug_en' => $slug, 'status' => CourseStatus::Published,
        'access_mode' => Course::ACCESS_FREE,
        ...$fields,
    ]);
}

function t204Price(Course $course, int $major, string $market = 'EG', string $currency = 'EGP'): void
{
    CoursePrice::query()->create([
        'course_id' => $course->getKey(), 'market' => $market, 'currency' => $currency, 'amount_minor' => $major * 100,
    ]);
}

/** Price fixture shared by the price matrix: 100, 500 and 900 EGP, a free course and a subscription-only course. */
function t204PriceFixture(): array
{
    $hundred = t204Course('hundred', ['access_mode' => Course::ACCESS_PAID]);
    t204Price($hundred, 100);
    $five = t204Course('five-hundred', ['access_mode' => Course::ACCESS_PAID]);
    t204Price($five, 500);
    $nine = t204Course('nine-hundred', ['access_mode' => Course::ACCESS_PAID]);
    t204Price($nine, 900);
    $free = t204Course('free-course', ['access_mode' => Course::ACCESS_FREE]);
    $subscription = t204Course('subscription-course', ['access_mode' => Course::ACCESS_SUBSCRIPTION]);

    return compact('hundred', 'five', 'nine', 'free', 'subscription');
}

// ---------- A2 price matrix ----------

it('filters by an EGP price range and excludes subscription-only courses from any range', function (): void {
    t204PriceFixture();

    $this->get('/en/courses?price_min=400&price_max=1000')->assertOk()
        ->assertSee('Five-hundred')->assertSee('Nine-hundred')
        ->assertDontSee('Hundred')->assertDontSee('Free-course')->assertDontSee('Subscription-course');
})->group('T-P2-04', 'A2');

it('treats a free course as price 0 so price_max=0 keeps only free courses', function (): void {
    t204PriceFixture();

    $html = (string) $this->get('/en/courses?price_max=0')->assertOk()->getContent();

    expect($html)->toContain('Free-course')
        ->not->toContain('Five-hundred')
        ->not->toContain('Nine-hundred')
        ->not->toContain('Hundred<')
        ->not->toContain('Subscription-course');
})->group('T-P2-04', 'A2');

it('combines free and paid courses in a bounded range starting at 0', function (): void {
    t204PriceFixture();

    $this->get('/en/courses?price_min=0&price_max=200')->assertOk()
        ->assertSee('Free-course')->assertSee('Hundred')
        ->assertDontSee('Five-hundred')->assertDontSee('Subscription-course');
})->group('T-P2-04', 'A2');

it('swaps reversed price bounds instead of returning an empty or failing page', function (): void {
    t204PriceFixture();

    $this->get('/en/courses?price_min=1000&price_max=400')->assertOk()
        ->assertSee('Five-hundred')->assertSee('Nine-hundred')->assertDontSee('Hundred<');
})->group('T-P2-04', 'A2');

it('rejects non-numeric or negative price bounds with a redirect or 422, never a server error', function (string $query): void {
    t204PriceFixture();

    $response = $this->get('/en/courses?'.$query);

    expect($response->getStatusCode())->toBeIn([302, 422])
        ->and($response->getStatusCode())->not->toBe(500);
})->with(['price_min=abc', 'price_max=-5', 'price_min=-1&price_max=10', 'price_min=1e999'])
    ->group('T-P2-04', 'A2');

it('renders the EGP price in the market format with Latin digits, and never for subscription-only courses', function (): void {
    t204PriceFixture();

    $en = (string) $this->withSession(['locale' => 'en'])->get('/en/courses')->assertOk()->getContent();
    expect($en)->toContain('EGP')->toContain('500')
        ->not->toContain('SAR');

    $ar = (string) $this->withSession(['locale' => 'ar'])->get('/ar/courses')->assertOk()->getContent();
    expect($ar)->toContain('500')
        ->not->toContain('٥٠٠')
        ->not->toContain('SAR');
})->group('T-P2-04', 'A2');

it('never uses the Saudi (SA) price row, even when it is the only price', function (): void {
    $course = t204Course('saudi-only', ['access_mode' => Course::ACCESS_PAID]);
    CoursePrice::query()->create(['course_id' => $course->getKey(), 'market' => 'SA', 'currency' => 'SAR', 'amount_minor' => 30000]);

    $this->get('/en/courses?price_min=100')->assertOk()->assertDontSee('Saudi-only');
    $this->get('/en/courses')->assertOk()->assertSee('Saudi-only')->assertDontSee('SAR');
})->group('T-P2-04', 'A2');

// ---------- A2 sort ----------

it('sorts price_asc with free first and subscription-only last', function (): void {
    t204PriceFixture();

    $this->get('/en/courses?sort=price_asc')->assertOk()
        ->assertSeeInOrder(['Free-course', 'Hundred', 'Five-hundred', 'Nine-hundred', 'Subscription-course']);
})->group('T-P2-04', 'A2');

it('sorts price_desc with subscription-only still last', function (): void {
    t204PriceFixture();

    $this->get('/en/courses?sort=price_desc')->assertOk()
        ->assertSeeInOrder(['Nine-hundred', 'Five-hundred', 'Hundred', 'Free-course', 'Subscription-course']);
})->group('T-P2-04', 'A2');

it('breaks price ties with the newest course first', function (): void {
    $older = t204Course('tie-older', ['access_mode' => Course::ACCESS_PAID]);
    t204Price($older, 250);
    $newer = t204Course('tie-newer', ['access_mode' => Course::ACCESS_PAID]);
    t204Price($newer, 250);
    $older->forceFill(['created_at' => now()->subDays(3)])->save();
    $newer->forceFill(['created_at' => now()->subDay()])->save();

    $this->get('/en/courses?sort=price_asc')->assertOk()->assertSeeInOrder(['Tie-newer', 'Tie-older']);
})->group('T-P2-04', 'A2');

it('ranks popular courses by distinct active learners, ignoring expired and revoked grants', function (): void {
    $this->freezeTime();
    [$learnerA, $learnerB] = User::factory()->count(2)->create()->all();

    $crowded = t204Course('pop-crowded');
    $dup = t204Course('pop-duplicate');
    $expired = t204Course('pop-expired');
    $none = t204Course('pop-none');

    // Two active grants for learner A on one course still count as one learner.
    foreach ([[$crowded, $learnerA, 'x1'], [$crowded, $learnerA, 'x2'], [$crowded, $learnerB, 'x3']] as [$course, $learner, $ref]) {
        Entitlement::query()->create(['course_id' => $course->id, 'learner_id' => $learner->id, 'source_type' => EntitlementSource::ManualGrant,
            'source_ref' => $ref, 'status' => Entitlement::ACTIVE, 'starts_at' => now()->subDay()]);
    }
    Entitlement::query()->create(['course_id' => $dup->id, 'learner_id' => $learnerA->id, 'source_type' => EntitlementSource::ManualGrant,
        'source_ref' => 'd1', 'status' => Entitlement::ACTIVE, 'starts_at' => now()->subDay()]);
    Entitlement::query()->create(['course_id' => $expired->id, 'learner_id' => $learnerA->id, 'source_type' => EntitlementSource::ManualGrant,
        'source_ref' => 'e1', 'status' => Entitlement::ACTIVE, 'starts_at' => now()->subDays(10), 'ends_at' => now()->subDay()]);
    Entitlement::query()->create(['course_id' => $expired->id, 'learner_id' => $learnerB->id, 'source_type' => EntitlementSource::ManualGrant,
        'source_ref' => 'e2', 'status' => Entitlement::REVOKED, 'starts_at' => now()->subDay()]);

    $this->get('/en/courses?sort=popular')->assertOk()
        ->assertSeeInOrder(['Pop-crowded', 'Pop-duplicate'])
        ->assertSeeInOrder(['Pop-duplicate', 'Pop-none'])
        ->assertSeeInOrder(['Pop-expired', 'Pop-none']);
})->group('T-P2-04', 'A2');

// ---------- A2 availability ----------

function t204Cohort(Course $course, array $fields = []): Cohort
{
    return $course->cohorts()->create([
        'name' => 'Intake', 'status' => Cohort::OPEN, 'starts_on' => now('Africa/Cairo')->addDays(40)->toDateString(),
        ...$fields,
    ]);
}

it('keeps only courses with an open cohort that has room, and labels seat counts with the plural key', function (): void {
    $this->freezeTime();
    $full = t204Course('avail-full');
    $cohort = t204Cohort($full, ['capacity' => 1]);
    $cohort->members()->create(['learner_id' => User::factory()->create()->id, 'status' => CohortMember::SEATED]);

    $oneLeft = t204Course('avail-one-left');
    t204Cohort($oneLeft, ['capacity' => 2])->members()->create(['learner_id' => User::factory()->create()->id, 'status' => CohortMember::SEATED]);

    $closed = t204Course('avail-closed');
    t204Cohort($closed, ['status' => Cohort::CLOSED]);

    $notYet = t204Course('avail-not-yet');
    t204Cohort($notYet, ['enrollment_opens_on' => now('Africa/Cairo')->addDays(5)->toDateString()]);

    $ended = t204Course('avail-ended');
    t204Cohort($ended, ['enrollment_closes_on' => now('Africa/Cairo')->subDay()->toDateString()]);

    $open = t204Course('avail-recorded');

    $seats = (string) $this->get('/en/courses?availability=seats')->assertOk()->getContent();
    expect($seats)->toContain('Avail-one-left')->not->toContain('Avail-full')->not->toContain('Avail-closed')
        ->not->toContain('Avail-not-yet')->not->toContain('Avail-ended');

    $this->get('/en/courses?availability=open')->assertOk()
        ->assertSee('Avail-recorded')->assertSee('Avail-one-left')
        ->assertDontSee('Avail-full')->assertDontSee('Avail-closed')->assertDontSee('Avail-not-yet');

    $this->get('/en/courses')->assertOk()
        ->assertSee(trans_choice('catalog.seats_left', 1, ['count' => 1], 'en'))
        ->assertSee('Enrolment closed');
})->group('T-P2-04', 'A2');

it('shows the starts-on badge only for a cohort starting within 14 days', function (): void {
    $this->freezeTime();
    $soon = t204Course('soon-five-days');
    t204Cohort($soon, ['starts_on' => now('Africa/Cairo')->addDays(5)->toDateString()]);
    $later = t204Course('later-forty-days');
    t204Cohort($later, ['starts_on' => now('Africa/Cairo')->addDays(40)->toDateString()]);

    $this->get('/en/courses?availability=soon')->assertOk()
        ->assertSee('Soon-five-days')->assertDontSee('Later-forty-days')
        ->assertSee(__('catalog.starts_on', ['date' => now('Africa/Cairo')->addDays(5)->locale('en')->isoFormat('LL')], 'en'));
})->group('T-P2-04', 'A2');

it('treats a recorded course without cohorts as open and never marks a subscription-only course open', function (): void {
    $this->freezeTime();
    t204Course('plain-recorded');
    t204Course('plain-subscription', ['access_mode' => Course::ACCESS_SUBSCRIPTION]);

    $this->get('/en/courses?availability=open')->assertOk()
        ->assertSee('Plain-recorded')->assertDontSee('Plain-subscription');
})->group('T-P2-04', 'A2');

it('lists subscription-only courses under the subscription availability filter', function (): void {
    t204Course('sub-only', ['access_mode' => Course::ACCESS_SUBSCRIPTION]);
    t204Course('sub-free');

    $this->get('/en/courses?availability=subscription')->assertOk()
        ->assertSee('Sub-only')->assertDontSee('Sub-free');
})->group('T-P2-04', 'A2');

// ---------- A2 combinations and draft exclusion ----------

it('keeps price, sort and availability in the URL across pagination and never lists drafts or pending courses', function (): void {
    for ($i = 1; $i <= 13; $i++) {
        $course = t204Course('paged-'.$i, ['access_mode' => Course::ACCESS_PAID]);
        t204Price($course, 100 + $i);
    }
    t204Course('hidden-draft', ['status' => CourseStatus::Draft]);
    t204Course('hidden-pending', ['status' => CourseStatus::PendingReview]);

    $query = ['price_min' => 100, 'sort' => 'price_desc', 'availability' => 'open'];
    $response = $this->get('/en/courses?'.http_build_query($query))->assertOk();
    $response->assertDontSee('Hidden-draft')->assertDontSee('Hidden-pending');

    $html = (string) $response->getContent();
    expect($html)->toContain('price_desc')->toContain('availability=open')->toContain('price_min=100');

    $page2 = (string) $this->get('/en/courses?'.http_build_query($query + ['page' => 2]))->assertOk()->getContent();
    expect($page2)->toContain('sort=price_desc')->toContain('price_min=100')->not->toContain('Hidden-pending');
})->group('T-P2-04', 'A2');

// ---------- A2 empty state and chips ----------

it('shows the empty state with a clear link, and each chip removes only its own parameter', function (): void {
    t204PriceFixture();

    $this->get('/en/courses?price_min=5000')->assertOk()
        ->assertSee('data-catalog-empty', false)
        ->assertSee('href="'.route('public.catalog.index', ['publicLocale' => 'en']).'"', false);

    $html = (string) $this->get('/en/courses?price_min=400&price_max=1000&sort=price_asc')->assertOk()->getContent();
    $withoutMinimum = route('public.catalog.index', ['publicLocale' => 'en', 'price_max' => 1000, 'sort' => 'price_asc']);
    expect($html)->toContain('data-active-filters')->toContain('href="'.e($withoutMinimum).'"');

    $this->get('/en/courses?price_min=400&price_max=1000')->assertOk()
        ->assertSee('data-active-filters', false);
})->group('T-P2-04', 'A2');

// ---------- A2 SEO robots and canonical (catalog meta) ----------

it('marks only the plain first page as index and every filtered or paged view noindex, follow', function (string $url, string $robots): void {
    t204PriceFixture();

    $this->get($url)->assertOk()->assertSee('<meta name="robots" content="'.$robots.'">', false);
})->with([
    'plain page 1' => ['/en/courses', 'index, follow'],
    'search' => ['/en/courses?q=hundred', 'noindex, follow'],
    'price sort' => ['/en/courses?sort=price_asc', 'noindex, follow'],
    'access filter' => ['/en/courses?access=free', 'noindex, follow'],
    'page 2' => ['/en/courses?page=2', 'noindex, follow'],
    'empty result' => ['/en/courses?price_min=5000', 'noindex, follow'],
])->group('T-P2-04', 'A2', 'YG-WEB-003');

it('keeps the canonical on the base catalog and hreflang pairs for ar and en', function (): void {
    t204PriceFixture();

    $html = (string) $this->get('/en/courses?q=hundred&sort=price_asc&page=2')->assertOk()->getContent();
    preg_match('~<link rel="canonical" href="([^"]+)"~', $html, $canonical);
    expect($canonical[1] ?? null)->toBe(url('/en/courses'))
        ->and(substr_count($html, 'rel="canonical"'))->toBe(1);

    $this->get('/en/courses')->assertOk()
        ->assertSee('hreflang="ar"', false)
        ->assertSee('hreflang="en"', false)
        ->assertSee(url('/ar/courses'), false)
        ->assertSee(url('/en/courses'), false);
})->group('T-P2-04', 'A2', 'YG-WEB-003');

// ---------- A2 N+1 ----------

it('keeps the query count constant as the number of listed courses grows', function (): void {
    $instructor = User::factory()->create(['status' => 'active']);
    $count = function (int $courses) use ($instructor): int {
        // Warm-up request: the first request in a test also reads the settings cache, which is not per-course work.
        $this->get('/en/courses?price_min=1')->assertOk();

        for ($i = 0; $i < $courses; $i++) {
            $course = t204Course('nplus-'.uniqid(), ['access_mode' => Course::ACCESS_PAID]);
            $course->instructors()->attach($instructor);
            t204Price($course, 200);
            t204Cohort($course);
            t204Cohort($course, ['capacity' => 5]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/en/courses?price_min=1')->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    $few = $count(2);
    $many = $count(12);

    expect($many)->toBe($few);
})->group('T-P2-04', 'A2');

// ---------- A2 translations and RTL ----------

it('has translation parity for the catalog keys', function (): void {
    $this->artisan('app:check-translations')->assertExitCode(0);
})->group('T-P2-04', 'A2');

it('passes the RTL check', function (): void {
    $this->artisan('app:check-rtl')->assertExitCode(0);
})->group('T-P2-04', 'A2');

// ---------- Invariant: catalog never reads entitlements outside its module ----------

it('keeps the catalog source free of direct entitlements table access', function (): void {
    $files = [
        base_path('app/Domain/Catalog/Queries/CatalogQuery.php'),
        base_path('app/Domain/Cohorts/CohortAvailability.php'),
        base_path('app/Http/Controllers/PublicCatalogController.php'),
        base_path('app/Domain/Seo/CatalogSeo.php'),
    ];
    foreach ($files as $file) {
        expect(file_get_contents($file))->not->toMatch('~Entitlement::query|DB::table\(.entitlements.|\bentitlements\b\s*\(~');
    }
})->group('T-P2-04', 'A2', 'I01');
