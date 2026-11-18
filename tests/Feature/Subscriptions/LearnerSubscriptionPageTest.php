<?php

declare(strict_types=1);

// T-P8-GAPS | PRD 17, 29.7 | A1 A2 A3 A4 A5

use App\Domain\Commerce\Actions\CreateOrder;
use App\Domain\Commerce\Actions\GrantOrderEntitlements;
use App\Domain\Commerce\Actions\TransitionOrder;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\ProductType;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\Shared\Markets\Market;
use App\Domain\Subscriptions\Actions\ExpireSubscriptions;
use App\Domain\Subscriptions\Actions\SavePlan;
use App\Domain\Subscriptions\Subscription;
use App\Domain\Subscriptions\SubscriptionPeriod;
use App\Domain\Subscriptions\SubscriptionPlan;
use App\Domain\Subscriptions\SubscriptionStatus;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Carbon::setTestNow('2026-11-18 09:00:00');
});

afterEach(fn () => Carbon::setTestNow());

// ---------------------------------------------------------------------------
// Helpers (prefix tp8gs)
// ---------------------------------------------------------------------------

function tp8gsUser(string $role = 'student', string $locale = 'en'): User
{
    return User::factory()->create(['status' => 'active', 'locale' => $locale])->assignRole($role);
}

function tp8gsPlan(string $nameEn = 'Learner Page Plan'): SubscriptionPlan
{
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة الصفحة', 'en' => 'Learner Page Course '.uniqid()],
        'slug_ar' => 'tp8gs-ar-'.uniqid(), 'slug_en' => 'tp8gs-en-'.uniqid(),
        'status' => 'published', 'access_mode' => 'subscription_only',
    ]);

    return app(SavePlan::class)(tp8gsUser('admin'), [
        'name_ar' => $nameEn, 'name_en' => $nameEn, 'term' => 'monthly', 'is_active' => true,
        'prices' => [Market::Egypt->value => 5000], 'course_ids' => [$course->id],
    ]);
}

/** Pays a plan order for the learner (paid through the state machine and granted) and returns [order, subscription]. */
function tp8gsActivate(User $learner, SubscriptionPlan $plan, ?User $payer = null): array
{
    $order = app(CreateOrder::class)($payer ?? $learner, Market::Egypt, [new OrderLine(ProductType::Plan, $plan->id, $learner)], 'tp8gs-'.uniqid());
    app(TransitionOrder::class)($order, OrderStatus::Paid);
    app(GrantOrderEntitlements::class)($order->refresh(), EntitlementSource::Purchase);

    return [$order->refresh(), Subscription::query()->where(['plan_id' => $plan->id, 'learner_id' => $learner->id])->firstOrFail()];
}

function tp8gsPeriod(Subscription $subscription): SubscriptionPeriod
{
    return $subscription->latestPeriod();
}

// ---------------------------------------------------------------------------
// A5: /my/subscription
// ---------------------------------------------------------------------------

it('A5 a guest is redirected to the login page from /my/subscription', function (): void {
    $this->get(route('subscription.index'))->assertRedirect(route('login.page'));
})->group('T-P8-GAPS', 'A5');

it('A5 the page shows the plan, the paid dates, the grace end and an active badge in English', function (): void {
    $plan = tp8gsPlan();
    $learner = tp8gsUser('student', 'en');
    [, $subscription] = tp8gsActivate($learner, $plan);
    $period = tp8gsPeriod($subscription);

    $this->actingAs($learner)->get(route('subscription.index'))
        ->assertOk()
        ->assertSee('My subscription')
        ->assertSee($plan->name)
        ->assertSee('data-state="active"', false)
        ->assertSee(__('subscriptions.state_active', [], 'en'))
        ->assertSee(__('subscriptions.paid_from', [], 'en'))
        ->assertSee(__('subscriptions.period_end', [], 'en'))
        ->assertSee(__('subscriptions.grace_until', [], 'en'))
        ->assertSee($period->ends_at->copy()->locale('en')->isoFormat('LL'))
        ->assertSee($period->grace_ends_at->copy()->locale('en')->isoFormat('LL'));
})->group('T-P8-GAPS', 'A5');

it('A5 the page shows the same state in Arabic for an Arabic-speaking account', function (): void {
    $plan = tp8gsPlan();
    $learner = tp8gsUser('student', 'ar');
    tp8gsActivate($learner, $plan);

    $this->actingAs($learner)->get(route('subscription.index'))
        ->assertOk()
        ->assertSee('dir="rtl"', false)
        ->assertSee(__('subscriptions.state_active', [], 'ar'))
        ->assertSee(__('subscriptions.my_title', [], 'ar'));
})->group('T-P8-GAPS', 'A5');

it('A5 the grace, cancelled and expired badges show in the page', function (): void {
    $plan = tp8gsPlan();
    $learner = tp8gsUser();
    [, $subscription] = tp8gsActivate($learner, $plan);
    $period = tp8gsPeriod($subscription);

    // Period over, inside the grace days: access still open.
    Carbon::setTestNow($period->ends_at->copy()->addDay());
    $this->actingAs($learner)->get(route('subscription.index'))
        ->assertOk()->assertSee('data-state="grace"', false)->assertSee(__('subscriptions.state_grace'));

    // Cancelled while still in the paid period.
    Carbon::setTestNow('2026-11-18 09:00:00');
    $this->actingAs($learner)->post(route('subscription.cancel', $subscription))->assertRedirect(route('subscription.index'));
    $this->actingAs($learner)->get(route('subscription.index'))
        ->assertOk()->assertSee('data-state="cancelled"', false)->assertSee(__('subscriptions.state_cancelled'));

    // Past the grace days the subscription expires.
    Carbon::setTestNow($period->grace_ends_at->copy()->addDay());
    app(ExpireSubscriptions::class)();
    $this->actingAs($learner)->get(route('subscription.index'))
        ->assertOk()->assertSee('data-state="expired"', false)->assertSee(__('subscriptions.state_expired'));
})->group('T-P8-GAPS', 'A5');

it('A5 a parent sees the child subscription, and another account subscription is absent and 404 on renew and cancel', function (): void {
    $plan = tp8gsPlan();
    $parent = tp8gsUser('parent');
    $child = tp8gsUser();
    GuardianChildLink::query()->create([
        'guardian_id' => $parent->id, 'child_id' => $child->id, 'status' => GuardianChildLink::STATUS_VERIFIED,
        'method' => GuardianChildLink::METHOD_CREATED_BY_PARENT, 'verified_at' => now(),
    ]);
    [, $childSubscription] = tp8gsActivate($child, $plan, $parent);

    $stranger = tp8gsUser();
    $stranger->update(['locale' => 'en']);
    [, $foreign] = tp8gsActivate(tp8gsUser(), $plan);

    $this->actingAs($parent)->get(route('subscription.index'))
        ->assertOk()
        ->assertSee('data-subscription="'.$childSubscription->id.'"', false)
        ->assertSee(__('subscriptions.subscriber'))
        ->assertDontSee('data-subscription="'.$foreign->id.'"', false);

    $this->actingAs($stranger)->get(route('subscription.index'))
        ->assertOk()
        ->assertDontSee('data-subscription="'.$childSubscription->id.'"', false)
        ->assertDontSee('data-subscription="'.$foreign->id.'"', false);

    $this->actingAs($stranger)->post(route('subscription.renew', $childSubscription))->assertNotFound();
    $this->actingAs($stranger)->post(route('subscription.cancel', $childSubscription))->assertNotFound();
    $this->actingAs($stranger)->post(route('subscription.cancel', $foreign))->assertNotFound();
    expect($childSubscription->refresh()->cancelled_at)->toBeNull();
})->group('T-P8-GAPS', 'A5');

it('A5 inside the renewal window the renew button creates one pending plan order and redirects to checkout', function (): void {
    $plan = tp8gsPlan();
    $learner = tp8gsUser();
    [, $subscription] = tp8gsActivate($learner, $plan);
    $period = tp8gsPeriod($subscription);

    Carbon::setTestNow($period->ends_at->copy()->subDays(2));
    $this->actingAs($learner)->get(route('subscription.index'))
        ->assertOk()->assertSee(route('subscription.renew', $subscription), false)->assertDontSee('data-renew-closed', false);

    $first = $this->actingAs($learner)->post(route('subscription.renew', $subscription));
    $order = Order::query()->where('payer_id', $learner->id)->where('status', OrderStatus::Pending->value)->latest('id')->firstOrFail();
    $first->assertRedirect(route('checkout.order', $order));

    $second = $this->actingAs($learner)->post(route('subscription.renew', $subscription));
    $second->assertRedirect(route('checkout.order', $order));

    expect(Order::query()->where('payer_id', $learner->id)->where('status', OrderStatus::Pending->value)->count())->toBe(1)
        ->and($order->items()->firstOrFail()->product_type)->toBe(ProductType::Plan);
})->group('T-P8-GAPS', 'A5');

it('A5 before the renewal window the renew button is absent and a forged renew returns commerce.already_subscribed', function (): void {
    $plan = tp8gsPlan();
    $learner = tp8gsUser();
    [, $subscription] = tp8gsActivate($learner, $plan);

    $this->actingAs($learner)->get(route('subscription.index'))
        ->assertOk()
        ->assertDontSee(route('subscription.renew', $subscription), false)
        ->assertSee('data-renew-closed', false);

    $before = Order::query()->count();
    $this->actingAs($learner)->post(route('subscription.renew', $subscription))
        ->assertSessionHasErrors(['order' => __('commerce.already_subscribed')]);
    expect(Order::query()->count())->toBe($before);
})->group('T-P8-GAPS', 'A5');

it('A5 cancel keeps access to the period end, flashes the notice, writes the audit and removes the button; a second cancel is a no-op', function (): void {
    $plan = tp8gsPlan();
    $learner = tp8gsUser();
    [, $subscription] = tp8gsActivate($learner, $plan);
    $period = tp8gsPeriod($subscription);

    $this->actingAs($learner)->post(route('subscription.cancel', $subscription))
        ->assertRedirect(route('subscription.index'))
        ->assertSessionHas('subscription_notice', __('subscriptions.my_cancel_done', [], 'en'));

    expect($subscription->refresh()->cancelled_at)->not->toBeNull()
        ->and($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($period->refresh()->ends_at->toIso8601String())->toBe($period->ends_at->toIso8601String());
    expect(Activity::query()->where('event', 'subscription.cancelled')->where('subject_id', $subscription->getKey())->count())->toBe(1);

    $this->actingAs($learner)->get(route('subscription.index'))
        ->assertOk()
        ->assertDontSee('data-cancel', false)
        ->assertSee(__('subscriptions.my_cancel_done', [], 'en'), false);

    $cancelledAt = $subscription->refresh()->cancelled_at->toIso8601String();
    $this->actingAs($learner)->post(route('subscription.cancel', $subscription))->assertRedirect(route('subscription.index'));

    expect($subscription->refresh()->cancelled_at->toIso8601String())->toBe($cancelledAt)
        ->and(Activity::query()->where('event', 'subscription.cancelled')->where('subject_id', $subscription->getKey())->count())->toBe(1);
})->group('T-P8-GAPS', 'A5');

it('A5 the payment history page links to the subscription page', function (): void {
    $learner = tp8gsUser();

    $this->actingAs($learner)->get(route('payments.history'))
        ->assertOk()
        ->assertSee(route('subscription.index'), false);
})->group('T-P8-GAPS', 'A5');
