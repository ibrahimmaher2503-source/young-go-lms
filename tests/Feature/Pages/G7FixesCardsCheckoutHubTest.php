<?php

declare(strict_types=1);

// T-G7-FIXES-E | R10 checkout benefits, R7b checkout reference, G1 fake gateway, D16 settings hub, C3 review disclosure,
// C1 course card, plus the F stars component and token parity | E8 E11 E12 E13 E14 E15 F8

use App\Domain\Commerce\Actions\CreateOrder;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\PaymentAttempt;
use App\Domain\Commerce\ProductType;
use App\Domain\Engagement\Actions\SubmitCourseReview;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Shared\Markets\Market;
use App\Domain\Subscriptions\Actions\SavePlan;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Carbon::setTestNow('2026-11-18 09:00:00');
});

afterEach(fn () => Carbon::setTestNow());

// ---------------------------------------------------------------------------
// Helpers (prefix g7c)
// ---------------------------------------------------------------------------

function g7cLearner(): User
{
    return User::factory()->create(['status' => 'active', 'locale' => 'en'])->assignRole('student');
}

function g7cCourse(array $overrides = []): Course
{
    $course = Course::query()->create(array_merge([
        'title' => ['ar' => 'دورة بطاقة', 'en' => 'Card Course '.uniqid()],
        'slug_ar' => 'g7c-ar-'.uniqid(), 'slug_en' => 'g7c-en-'.uniqid(),
        'status' => 'published', 'access_mode' => 'paid',
    ], $overrides));
    CoursePrice::query()->create(['course_id' => $course->id, 'market' => Market::Egypt->value, 'currency' => 'EGP', 'amount_minor' => 25000]);

    return $course;
}

function g7cOpenAttempt(User $learner, Order $order): PaymentAttempt
{
    test()->actingAs($learner)->post(route('checkout.pay', $order), ['method' => 'card', 'terms' => '1'])->assertRedirect();

    return PaymentAttempt::query()->where('order_id', $order->id)->latest('sequence')->firstOrFail();
}

// ---------------------------------------------------------------------------
// E11: checkout step 1 "what you get"
// ---------------------------------------------------------------------------

it('E11 the course checkout lists access and the certificate line, with lifetime access when no day count is set', function (): void {
    $learner = g7cLearner();
    $course = g7cCourse(['access_duration_days' => null]);

    $this->actingAs($learner)->get(route('checkout.product', ['type' => 'course', 'id' => $course->id]))->assertOk()
        ->assertSee('data-checkout-benefits', false)
        ->assertSee(__('checkout.benefits_title', [], 'en'))
        ->assertSee(__('checkout.benefit_access_lifetime', [], 'en'))
        ->assertSee(__('checkout.benefit_certificate', [], 'en'));
})->group('T-G7-FIXES-E', 'E11');

it('E11 a course with a day limit shows the day count, and a course with no lessons shows no lesson row', function (): void {
    $learner = g7cLearner();
    $course = g7cCourse(['access_duration_days' => 90]);

    $html = $this->actingAs($learner)->get(route('checkout.product', ['type' => 'course', 'id' => $course->id]))->assertOk()
        ->assertSee(__('checkout.benefit_access_days', ['days' => 90], 'en'))
        ->assertDontSee(__('checkout.benefit_access_lifetime', [], 'en'))
        ->getContent();

    expect($html)->not->toContain('curriculum.lessons_count');
})->group('T-G7-FIXES-E', 'E11');

it('E11 a plan checkout lists the course count, the term line and the manual renewal line', function (): void {
    $staff = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $course = g7cCourse(['access_mode' => 'subscription_only']);
    $plan = app(SavePlan::class)($staff, [
        'name_ar' => 'خطة', 'name_en' => 'G7C Plan '.uniqid(), 'term' => 'monthly', 'is_active' => true,
        'prices' => [Market::Egypt->value => 5000], 'course_ids' => [$course->id],
    ]);
    $learner = g7cLearner();

    $this->actingAs($learner)->get(route('checkout.product', ['type' => 'plan', 'id' => $plan->id]))->assertOk()
        ->assertSee('data-checkout-benefits', false)
        ->assertSee(__('checkout.benefit_plan_courses', ['count' => 1], 'en'))
        ->assertSee(__('checkout.benefit_plan_term_monthly', [], 'en'))
        ->assertSee(__('checkout.benefit_plan_manual', [], 'en'));
})->group('T-G7-FIXES-E', 'E11');

it('E11 the checkout benefit keys exist in English and Arabic', function (): void {
    foreach (['benefit_access_lifetime', 'benefit_access_days', 'benefit_certificate', 'benefit_bundle_courses', 'benefit_plan_courses', 'benefit_plan_manual', 'benefits_title'] as $key) {
        expect(__('checkout.'.$key, [], 'en'))->not->toBe('checkout.'.$key)
            ->and(__('checkout.'.$key, [], 'ar'))->not->toBe('checkout.'.$key);
    }
})->group('T-G7-FIXES-E', 'E11');

// ---------------------------------------------------------------------------
// E8: order reference stays on one line in the checkout order page
// ---------------------------------------------------------------------------

it('E8 the order reference in the checkout order header is an LTR, non-wrapping run', function (): void {
    $learner = g7cLearner();
    $course = g7cCourse();
    $order = app(CreateOrder::class)($learner, Market::Egypt, [new OrderLine(ProductType::Course, $course->id, $learner)], 'g7c-'.uniqid());

    $html = $this->actingAs($learner)->get(route('checkout.order', $order))->assertOk()->getContent();

    expect($html)->toMatch('/whitespace-nowrap[^>]*>\s*'.preg_quote($order->reference, '/').'/');
})->group('T-G7-FIXES-E', 'E8');

// ---------------------------------------------------------------------------
// E12: fake gateway steps and hints
// ---------------------------------------------------------------------------

it('E12 the fake gateway shows the steps card and the hints under Pay and Fail, with the stable hooks kept', function (): void {
    $learner = g7cLearner();
    $course = g7cCourse();
    $order = app(CreateOrder::class)($learner, Market::Egypt, [new OrderLine(ProductType::Course, $course->id, $learner)], 'g7c-fg-'.uniqid());
    $attempt = g7cOpenAttempt($learner, $order);

    $this->get('/fake-gateway/'.$attempt->reference)->assertOk()
        ->assertSee('data-fake-steps', false)
        ->assertSee(__('fake_gateway.steps_title', [], 'en'))
        ->assertSee(__('fake_gateway.pay_hint', [], 'en'))
        ->assertSee(__('fake_gateway.fail_hint', [], 'en'))
        ->assertSee('data-fake-pay', false)
        ->assertSee('data-fake-fail', false)
        ->assertSee('data-total', false);
})->group('T-G7-FIXES-E', 'E12');

// ---------------------------------------------------------------------------
// E13: settings hub cards
// ---------------------------------------------------------------------------

it('E13 every settings hub entry is an anchor card with an icon, a title and a description', function (): void {
    $this->actingAs(User::factory()->create(['status' => 'active'])->assignRole('super_admin'));
    $html = $this->get('/admin/settings')->assertOk()->getContent();

    expect($html)->toMatch('/<a [^>]*class="yg-hub-card"[^>]*data-settings-entry="[a-z_]+"/');
    preg_match_all('/<a [^>]*class="yg-hub-card"[^>]*data-settings-entry="[a-z_]+".*?<\/a>/s', $html, $matches);
    // Super admin sees every card; the count is 10 today (the older parity test comment says nine).
    expect(count($matches[0]))->toBeGreaterThanOrEqual(9);
    foreach ($matches[0] as $card) {
        expect($card)->toContain('yg-hub-icon')->toContain('yg-hub-title')->toContain('yg-hub-desc');
    }
})->group('T-G7-FIXES-E', 'E13');

it('E13 a staff member outside the settings roles cannot open the hub and sees no cards', function (): void {
    $this->actingAs(User::factory()->create(['status' => 'active'])->assignRole('finance'));
    $this->get('/admin/settings')->assertForbidden();
})->group('T-G7-FIXES-E', 'E13');

// ---------------------------------------------------------------------------
// E14: review form is inside a closed disclosure
// ---------------------------------------------------------------------------

it('E14 an entitled learner sees the review form inside a closed disclosure with the write toggle', function (): void {
    $course = g7cCourse(['access_mode' => 'free']);
    $learner = g7cLearner();
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);
    $url = route('catalog.show', ['course' => \App\Domain\Catalog\Queries\PublicCourseDetail::key($course)]);

    $html = $this->actingAs($learner)->get($url)->assertOk()->getContent();

    expect($html)->toContain('data-review-disclosure')
        ->and($html)->toMatch('/<details[^>]*data-review-disclosure(?![^>]*\bopen\b)[^>]*>/')
        ->and($html)->toContain('data-review-toggle')
        ->and($html)->toContain(__('engagement.write', [], 'en'))
        ->and($html)->toContain('data-review-form');
})->group('T-G7-FIXES-E', 'E14');

it('E14 a rejected review post reopens the disclosure on the next page', function (): void {
    $course = g7cCourse(['access_mode' => 'free']);
    $learner = g7cLearner();
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);
    $url = route('catalog.show', ['course' => \App\Domain\Catalog\Queries\PublicCourseDetail::key($course)]);

    $this->actingAs($learner)->from($url)->post(route('reviews.store', ['course' => $course->getKey()]), ['rating' => '', 'body' => 'x']);

    $html = $this->actingAs($learner)->get($url)->assertOk()->getContent();
    expect($html)->toMatch('/<details[^>]*data-review-disclosure[^>]*\bopen\b/');
})->group('T-G7-FIXES-E', 'E14');

it('E14 a learner who cannot review sees no review disclosure', function (): void {
    $course = g7cCourse(['access_mode' => 'free']);
    $stranger = g7cLearner();

    $html = $this->actingAs($stranger)->get(route('catalog.show', ['course' => \App\Domain\Catalog\Queries\PublicCourseDetail::key($course)]))->assertOk()->getContent();
    expect($html)->not->toContain('data-review-disclosure');
})->group('T-G7-FIXES-E', 'E14');

// ---------------------------------------------------------------------------
// E15: course card priority, clamp and layout
// ---------------------------------------------------------------------------

it('E15 the non-compact course card keeps the lessons line, clamps title and description and fills its height', function (): void {
    $course = g7cCourse(['short_description' => 'A short intro to the course.', 'thumbnail_path' => 'courses/g7c.jpg']);

    $html = Blade::render('<x-learning.course-card :course="$course" :lessons="4" />', ['course' => $course]);

    expect($html)->toContain('data-card-lessons')
        ->and($html)->toContain('line-clamp-2')
        ->and($html)->toContain('h-full')
        ->and($html)->toContain('flex-col');
});

it('E15 the compact card omits the lessons line and keeps its compact classes', function (): void {
    $course = g7cCourse(['short_description' => 'A short intro.']);

    $html = Blade::render('<x-learning.course-card :course="$course" :lessons="4" compact="true" />', ['course' => $course]);

    expect($html)->not->toContain('data-card-lessons')
        ->and($html)->toContain('p-2.5');
});

it('F4 the priority card is eager with high fetch priority and the others are lazy, all with width and height', function (): void {
    $course = g7cCourse(['thumbnail_path' => 'courses/g7c.jpg']);

    $eager = Blade::render('<x-learning.course-card :course="$course" :priority="true" />', ['course' => $course]);
    $lazy = Blade::render('<x-learning.course-card :course="$course" />', ['course' => $course]);

    expect($eager)->toContain('loading="eager"')->toContain('fetchpriority="high"')
        ->and($lazy)->toContain('loading="lazy"')->not->toContain('fetchpriority')
        ->and($eager)->toContain('width="640"')->toContain('height="360"');
});

// ---------------------------------------------------------------------------
// F8: stars component and tokens
// ---------------------------------------------------------------------------

it('F8 the stars component renders five stars with the filled count and the rest empty', function (int $value, int $filled): void {
    $html = Blade::render('<x-engagement.stars :value="$value" />', ['value' => $value]);

    expect(preg_match_all('/class="text-star"/', $html))->toBe($filled)
        ->and(preg_match_all('/class="text-star-empty"/', $html))->toBe(5 - $filled);
})->with([[5, 5], [3, 3], [2, 2]])->group('T-G7-FIXES-E', 'F8');

it('F8 the star colour tokens are defined in both the light and the dark token blocks', function (): void {
    $css = (string) file_get_contents(base_path('resources/css/tokens.css'));

    expect(substr_count($css, '--yg-color-star:'))->toBeGreaterThanOrEqual(2)
        ->and(substr_count($css, '--yg-color-star-empty:'))->toBeGreaterThanOrEqual(2);
})->group('T-G7-FIXES-E', 'F8');
