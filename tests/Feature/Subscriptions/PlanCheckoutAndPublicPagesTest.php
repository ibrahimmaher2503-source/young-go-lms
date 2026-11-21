<?php

declare(strict_types=1);

// T-P8-GAPS | PRD 17, 29.7 | A1 A2 A3 A4 A5

use App\Domain\Commerce\Actions\CreateOrder;
use App\Domain\Commerce\Actions\GrantOrderEntitlements;
use App\Domain\Commerce\Actions\SaveBundle;
use App\Domain\Commerce\Actions\TransitionOrder;
use App\Domain\Commerce\Bundle;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\PaymentAttempt;
use App\Domain\Commerce\Payments\FakePaymentGateway;
use App\Domain\Commerce\ProductType;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\Shared\Markets\Market;
use App\Domain\Shared\Money\Money;
use App\Domain\Shared\Money\PriceFormatter;
use App\Domain\Subscriptions\Actions\SavePlan;
use App\Domain\Subscriptions\Subscription;
use App\Domain\Subscriptions\SubscriptionPeriod;
use App\Domain\Subscriptions\SubscriptionPlan;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Carbon::setTestNow('2026-11-18 09:00:00');
});

afterEach(fn () => Carbon::setTestNow());

// ---------------------------------------------------------------------------
// Helpers (prefix tp8gp)
// ---------------------------------------------------------------------------

function tp8gpUser(string $role = 'student'): User
{
    return User::factory()->create(['status' => 'active', 'locale' => 'en'])->assignRole($role);
}

function tp8gpStaff(): User
{
    return User::factory()->create(['status' => 'active'])->assignRole('admin');
}

/** A published course that can be included in a plan (no individual price needed). */
function tp8gpCourse(string $status = 'published', string $title = ''): Course
{
    return Course::query()->create([
        'title' => ['ar' => 'دورة باقة '.uniqid(), 'en' => $title !== '' ? $title : 'Plan Course '.uniqid()],
        'slug_ar' => 'tp8gp-ar-'.uniqid(), 'slug_en' => 'tp8gp-en-'.uniqid(),
        'status' => $status, 'access_mode' => 'subscription_only',
    ]);
}

/** @param  list<Course>  $courses */
function tp8gpPlan(array $courses, int $priceMinor = 5000, bool $active = true, string $nameEn = '', string $nameAr = ''): SubscriptionPlan
{
    return app(SavePlan::class)(tp8gpStaff(), [
        'name_ar' => $nameAr !== '' ? $nameAr : 'باقة شهرية '.uniqid(), 'name_en' => $nameEn !== '' ? $nameEn : 'Monthly Plan '.uniqid(), 'term' => 'monthly', 'is_active' => $active,
        'prices' => [Market::Egypt->value => $priceMinor],
        'course_ids' => array_map(static fn (Course $c): int => $c->id, $courses),
    ]);
}

/** @param  list<Course>  $courses */
function tp8gpBundle(array $courses, int $priceMinor = 30000, bool $active = true, string $nameEn = ''): Bundle
{
    return app(SaveBundle::class)(tp8gpStaff(), [
        'name_ar' => $nameEn !== '' ? $nameEn : 'Course Bundle '.uniqid(), 'name_en' => $nameEn !== '' ? $nameEn : 'Course Bundle '.uniqid(), 'is_active' => $active,
        'prices' => [Market::Egypt->value => $priceMinor],
        'items' => array_map(static fn (Course $c): array => ['course_id' => $c->id, 'weight' => null], $courses),
    ]);
}

/** Starts the plan checkout over HTTP and returns the order the redirect points to. */
function tp8gpStart(User $payer, SubscriptionPlan $plan, ?User $learner = null, ?string $key = null): Order
{
    $learner ??= $payer;
    test()->actingAs($payer)->post(route('checkout.start'), [
        'type' => 'plan', 'product_id' => $plan->id, 'learner_id' => $learner->id, 'key' => $key ?? 'tp8gp-'.bin2hex(random_bytes(6)),
    ])->assertRedirect();

    return Order::query()->where('payer_id', $payer->id)->latest('id')->firstOrFail();
}

/** Pays an order through the Fake gateway journey: card attempt, then the signed webhook (the path real money takes). */
function tp8gpPayViaWebhook(User $payer, Order $order): void
{
    test()->actingAs($payer)->post(route('checkout.pay', $order), ['method' => 'card', 'terms' => '1'])->assertRedirect();
    $attempt = PaymentAttempt::query()->where('order_id', $order->id)->latest('sequence')->firstOrFail();
    $payload = ['id' => 'tp8gp-'.uniqid(), 'reference' => $attempt->reference, 'amount_minor' => $order->total_minor, 'currency' => 'EGP', 'success' => true];
    test()->postJson(route('webhooks.paymob'), $payload, ['X-Payment-Signature' => FakePaymentGateway::sign($payload)])->assertOk();
}

function tp8gpMoney(int $minor, string $locale): string
{
    return (new PriceFormatter)->format(Money::ofMinor($minor, 'EGP'), Market::Egypt, $locale);
}

/** A paid plan order for the learner (through the webhook), so the learner is covered for the next month. */
function tp8gpCoveredLearner(SubscriptionPlan $plan): User
{
    $learner = tp8gpUser();
    $order = tp8gpStart($learner, $plan);
    tp8gpPayViaWebhook($learner, $order);

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);

    return $learner;
}

// ---------------------------------------------------------------------------
// A3: plan checkout entry
// ---------------------------------------------------------------------------

it('A3 a signed-in learner opens the plan page and continues to one plan line at the plan price', function (): void {
    $course = tp8gpCourse();
    $plan = tp8gpPlan([$course], 5000, true, 'Learner Entry Plan');
    $learner = tp8gpUser();

    $this->actingAs($learner)->get(route('checkout.product', ['type' => 'plan', 'id' => $plan->id]))
        ->assertOk()
        ->assertSee('Learner Entry Plan')
        ->assertSee('data-checkout-learner', false);

    $order = tp8gpStart($learner, $plan);
    $this->actingAs($learner)->get(route('checkout.order', $order))->assertOk();

    $items = $order->items()->get();
    expect($items)->toHaveCount(1)
        ->and($items->first()->product_type)->toBe(ProductType::Plan)
        ->and($items->first()->product_id)->toBe($plan->id)
        ->and($items->first()->learner_id)->toBe($learner->id)
        ->and($order->total_minor)->toBe(5000);
})->group('T-P8-GAPS', 'A3');

it('A3 a parent picks a linked child as the learner and the order line belongs to the child', function (): void {
    $course = tp8gpCourse();
    $plan = tp8gpPlan([$course], 5000);
    $parent = tp8gpUser('parent');
    $child = tp8gpUser();
    GuardianChildLink::query()->create([
        'guardian_id' => $parent->id, 'child_id' => $child->id, 'status' => GuardianChildLink::STATUS_VERIFIED,
        'method' => GuardianChildLink::METHOD_CREATED_BY_PARENT, 'verified_at' => now(),
    ]);

    $this->actingAs($parent)->get(route('checkout.product', ['type' => 'plan', 'id' => $plan->id]))
        ->assertOk()
        ->assertSee('value="'.$child->id.'"', false);

    $order = tp8gpStart($parent, $plan, $child);

    expect($order->payer_id)->toBe($parent->id)
        ->and($order->items()->firstOrFail()->learner_id)->toBe($child->id)
        ->and($order->total_minor)->toBe(5000);
})->group('T-P8-GAPS', 'A3');

it('A3 an inactive plan is 404 on the product page and the start is refused', function (): void {
    $plan = tp8gpPlan([tp8gpCourse()], 5000, false);
    $learner = tp8gpUser();

    $this->actingAs($learner)->get(route('checkout.product', ['type' => 'plan', 'id' => $plan->id]))->assertNotFound();
    $this->actingAs($learner)->post(route('checkout.start'), [
        'type' => 'plan', 'product_id' => $plan->id, 'learner_id' => $learner->id, 'key' => 'tp8gp-inactive-1',
    ])->assertNotFound();

    expect(Order::query()->count())->toBe(0);
})->group('T-P8-GAPS', 'A3');

it('A3 a plan without an Egypt price is 404 on the product page and the start is refused', function (): void {
    $plan = tp8gpPlan([tp8gpCourse()], 5000);
    $plan->prices()->delete();
    $learner = tp8gpUser();

    $this->actingAs($learner)->get(route('checkout.product', ['type' => 'plan', 'id' => $plan->id]))->assertNotFound();
    $this->actingAs($learner)->post(route('checkout.start'), [
        'type' => 'plan', 'product_id' => $plan->id, 'learner_id' => $learner->id, 'key' => 'tp8gp-noprice-1',
    ])->assertNotFound();

    expect(Order::query()->count())->toBe(0);
})->group('T-P8-GAPS', 'A3');

it('A3 a plan whose included courses are all unpublished is 404 on the product page and the start is refused', function (): void {
    $course = tp8gpCourse();
    $plan = tp8gpPlan([$course], 5000);
    $course->forceFill(['status' => 'draft'])->save();
    $learner = tp8gpUser();

    $this->actingAs($learner)->get(route('checkout.product', ['type' => 'plan', 'id' => $plan->id]))->assertNotFound();
    $this->actingAs($learner)->post(route('checkout.start'), [
        'type' => 'plan', 'product_id' => $plan->id, 'learner_id' => $learner->id, 'key' => 'tp8gp-draft-1',
    ])->assertNotFound();

    expect(Order::query()->count())->toBe(0);
})->group('T-P8-GAPS', 'A3');

it('A3 a learner covered beyond the renewal window is disabled on the plan page and a start returns commerce.already_subscribed', function (): void {
    $plan = tp8gpPlan([tp8gpCourse()], 5000);
    $learner = tp8gpCoveredLearner($plan);

    $this->actingAs($learner)->get(route('checkout.product', ['type' => 'plan', 'id' => $plan->id]))
        ->assertOk()
        ->assertSee(__('checkout.all_subscribed'));
    // The submit button itself must carry the disabled attribute (not just any "disabled" class).
    expect(preg_match('/<button type="submit"[^>]*\sdisabled[\s>]/', $this->actingAs($learner)->get(route('checkout.product', ['type' => 'plan', 'id' => $plan->id]))->getContent()))->toBe(1);

    $before = Order::query()->count();
    $this->actingAs($learner)->post(route('checkout.start'), [
        'type' => 'plan', 'product_id' => $plan->id, 'learner_id' => $learner->id, 'key' => 'tp8gp-covered-1',
    ])->assertSessionHasErrors(['order' => __('commerce.already_subscribed')]);

    expect(Order::query()->count())->toBe($before);
})->group('T-P8-GAPS', 'A3');

it('A3 a parent sees a linked child already covered by a plan as a disabled option with the already subscribed text', function (): void {
    $plan = tp8gpPlan([tp8gpCourse()], 5000);
    $parent = tp8gpUser('parent');
    $child = tp8gpUser();
    GuardianChildLink::query()->create([
        'guardian_id' => $parent->id, 'child_id' => $child->id, 'status' => GuardianChildLink::STATUS_VERIFIED,
        'method' => GuardianChildLink::METHOD_CREATED_BY_PARENT, 'verified_at' => now(),
    ]);
    $paid = app(CreateOrder::class)($parent, Market::Egypt, [new OrderLine(ProductType::Plan, $plan->id, $child)], 'tp8gp-child-covered');
    app(TransitionOrder::class)($paid, OrderStatus::Paid);
    app(GrantOrderEntitlements::class)($paid->refresh(), EntitlementSource::Purchase);

    $html = $this->actingAs($parent)->get(route('checkout.product', ['type' => 'plan', 'id' => $plan->id]))
        ->assertOk()
        ->assertSee(__('checkout.already_subscribed'))
        ->getContent();

    expect(preg_match('/<option value="'.$child->id.'"[^>]*\sdisabled[\s>]/', $html))->toBe(1);
    $this->actingAs($parent)->get(route('checkout.product', ['type' => 'plan', 'id' => $plan->id]))
        ->assertSee('value="'.$parent->id.'"', false);
})->group('T-P8-GAPS', 'A3');

it('A3 a second start for the same plan and learner returns the same pending order', function (): void {
    $plan = tp8gpPlan([tp8gpCourse()], 5000);
    $learner = tp8gpUser();

    $first = tp8gpStart($learner, $plan, null, 'tp8gp-first-key');
    $second = tp8gpStart($learner, $plan, null, 'tp8gp-second-key');

    expect($second->getKey())->toBe($first->getKey())
        ->and($first->status)->toBe(OrderStatus::Pending)
        ->and(Order::query()->where('payer_id', $learner->id)->count())->toBe(1);
})->group('T-P8-GAPS', 'A3');

it('A3 paying a plan order through the Fake webhook starts the period and grants the included courses', function (): void {
    $course = tp8gpCourse();
    $plan = tp8gpPlan([$course], 5000);
    $learner = tp8gpUser();
    $order = tp8gpStart($learner, $plan);

    expect(Subscription::query()->where('learner_id', $learner->id)->exists())->toBeFalse();

    tp8gpPayViaWebhook($learner, $order);

    $subscription = Subscription::query()->where(['plan_id' => $plan->id, 'learner_id' => $learner->id])->firstOrFail();
    $period = SubscriptionPeriod::query()->where('subscription_id', $subscription->getKey())->firstOrFail();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($period->starts_at->toIso8601String())->toBe(now()->toIso8601String())
        ->and($period->ends_at->toIso8601String())->toBe(now()->addMonth()->toIso8601String());
    app(EntitlementService::class)->flush();
    expect(app(EntitlementService::class)->canAccessCourse($learner, $course)->allowed)->toBeTrue();
})->group('T-P8-GAPS', 'A3');

// ---------------------------------------------------------------------------
// A4: public plans and bundle pages, sitemap
// ---------------------------------------------------------------------------

it('A4 the English plans page lists names, term, price, included courses, hreflang and canonical', function (): void {
    $course = tp8gpCourse('published', 'Included English Course');
    $plan = tp8gpPlan([$course], 5000, true, 'Public English Plan');

    $response = $this->get('/en/plans')->assertOk()
        ->assertSee('Public English Plan')
        ->assertSee(tp8gpMoney(5000, 'en'))
        ->assertSee('Included English Course')
        ->assertSee('href="'.url('/en/courses/'.$course->slug_en).'"', false)
        ->assertSee('rel="canonical"', false)
        ->assertSee('hreflang="ar"', false)
        ->assertSee('hreflang="en"', false)
        ->assertSee('data-plan="'.$plan->id.'"', false);

    expect($response->getContent())->toContain('dir="ltr"');
})->group('T-P8-GAPS', 'A4');

it('A4 the Arabic plans page shows the Arabic name, the monthly term label and renders right to left', function (): void {
    tp8gpPlan([tp8gpCourse()], 5000, true, 'Arabic Source Plan', 'خطة عربية للاختبار');

    $this->get('/ar/plans')->assertOk()
        ->assertSee('خطة عربية للاختبار')
        ->assertSee(__('subscriptions.per_monthly', [], 'ar'))
        ->assertSee('dir="rtl"', false);
    $this->get('/en/plans')->assertOk()
        ->assertSee('Arabic Source Plan')
        ->assertSee(__('subscriptions.per_monthly', [], 'en'));
})->group('T-P8-GAPS', 'A4');

it('A4 the plans page is 404 when no plan is sellable (no plans, inactive, or without a published course)', function (): void {
    $this->get('/en/plans')->assertNotFound();
    $this->get('/ar/plans')->assertNotFound();

    $course = tp8gpCourse();
    $inactive = tp8gpPlan([$course], 5000, false);
    expect($inactive->is_active)->toBeFalse();
    $this->get('/en/plans')->assertNotFound();

    $draftCourse = tp8gpCourse('draft');
    tp8gpPlan([$draftCourse], 5000);
    $this->get('/en/plans')->assertNotFound();
})->group('T-P8-GAPS', 'A4');

it('A4 the plans CTA is sign-in for guests and a checkout link for signed-in users', function (): void {
    $plan = tp8gpPlan([tp8gpCourse()], 5000);

    $this->get('/en/plans')->assertOk()
        ->assertSee('data-cta="sign-in"', false)
        ->assertDontSee('data-cta="buy-plan"', false);

    $learner = tp8gpUser();
    $this->actingAs($learner)->get('/en/plans')->assertOk()
        ->assertSee('data-cta="buy-plan"', false)
        ->assertSee(route('checkout.product', ['type' => 'plan', 'id' => $plan->id]), false);
})->group('T-P8-GAPS', 'A4');

it('A4 the English bundle page lists courses, price and a buy CTA, by id and by id with a name slug', function (): void {
    $a = tp8gpCourse('published', 'Bundle Course Alpha');
    $b = tp8gpCourse('published', 'Bundle Course Beta');
    $bundle = tp8gpBundle([$a, $b], 30000, true, 'Summer Bundle');

    $this->get('/en/bundles/'.$bundle->id)->assertOk()
        ->assertSee('Bundle Course Alpha')
        ->assertSee('Bundle Course Beta')
        ->assertSee(tp8gpMoney(30000, 'en'))
        ->assertSee('data-cta="sign-in"', false);

    $this->get('/en/bundles/'.$bundle->id.'-summer-bundle')->assertOk()->assertSee('Bundle Course Alpha');
    $this->get('/ar/bundles/'.$bundle->id)->assertOk()
        ->assertSee('href="'.url('/ar/courses').'"', false)
        ->assertSee('href="'.url('/ar/courses/'.$a->slug_ar).'"', false);

    $learner = tp8gpUser();
    $this->actingAs($learner)->get('/en/bundles/'.$bundle->id)->assertOk()
        ->assertSee('data-cta="buy-bundle"', false)
        ->assertSee(route('checkout.product', ['type' => 'bundle', 'id' => $bundle->id]), false);
})->group('T-P8-GAPS', 'A4');

it('A4 a bundle that is wrong, inactive, unpriced or holds a draft course is 404', function (): void {
    $published = tp8gpCourse();
    $bundle = tp8gpBundle([$published, tp8gpCourse()]);

    $this->get('/en/bundles/999999')->assertNotFound();

    $inactive = tp8gpBundle([tp8gpCourse(), tp8gpCourse()], 30000, false);
    $this->get('/en/bundles/'.$inactive->id)->assertNotFound();

    $unpriced = tp8gpBundle([tp8gpCourse(), tp8gpCourse()]);
    $unpriced->prices()->delete();
    $this->get('/en/bundles/'.$unpriced->id)->assertNotFound();

    $withDraft = tp8gpBundle([tp8gpCourse(), $draft = tp8gpCourse()]);
    $draft->forceFill(['status' => 'draft'])->save();
    $this->get('/en/bundles/'.$withDraft->id)->assertNotFound();

    $this->get('/en/bundles/'.$bundle->id)->assertOk();
})->group('T-P8-GAPS', 'A4');

it('A4 the sitemap lists the plans page and sellable bundles in every locale when they are sellable', function (): void {
    tp8gpPlan([tp8gpCourse()], 5000);
    $bundle = tp8gpBundle([tp8gpCourse(), tp8gpCourse()]);

    $this->get('/sitemap.xml')->assertOk()
        ->assertSee('/en/plans', false)
        ->assertSee('/ar/plans', false)
        ->assertSee('/en/bundles/'.$bundle->id, false)
        ->assertSee('/ar/bundles/'.$bundle->id, false);
})->group('T-P8-GAPS', 'A4');

it('A4 the sitemap lists neither the plans page nor bundles when nothing is sellable', function (): void {
    $this->get('/sitemap.xml')->assertOk()->assertDontSee('/en/plans', false)->assertDontSee('/en/bundles/', false);

    $plan = tp8gpPlan([tp8gpCourse()], 5000, false);
    $bundle = tp8gpBundle([tp8gpCourse(), tp8gpCourse()], 30000, false);
    expect($plan->is_active)->toBeFalse()->and($bundle->is_active)->toBeFalse();

    $this->get('/sitemap.xml')->assertOk()
        ->assertDontSee('/en/plans', false)
        ->assertDontSee('/en/bundles/'.$bundle->id, false);
})->group('T-P8-GAPS', 'A4');
