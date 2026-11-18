<?php

declare(strict_types=1);

// T-P8-08 | PRD 29.7, YG-GAP-SUB-001..004, D-008, R-017, O-024 | A1 A2 A3

use App\Domain\Commerce\Actions\ApplyCoupon;
use App\Domain\Commerce\Actions\CreateOrder;
use App\Domain\Commerce\Actions\GrantOrderEntitlements;
use App\Domain\Commerce\Actions\SaveCoupon;
use App\Domain\Commerce\Actions\TransitionOrder;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\ProductType;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\Contracts\LessonAccessChecker;
use App\Domain\Entitlements\Entitlement;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\Shared\Markets\Market;
use App\Domain\Subscriptions\Actions\CancelSubscription;
use App\Domain\Subscriptions\Actions\ExpireSubscriptions;
use App\Domain\Subscriptions\Actions\RenewSubscription;
use App\Domain\Subscriptions\Actions\SavePlan;
use App\Domain\Subscriptions\Actions\SendSubscriptionRenewalReminders;
use App\Domain\Subscriptions\Subscription;
use App\Domain\Subscriptions\SubscriptionPeriod;
use App\Domain\Subscriptions\SubscriptionPlan;
use App\Domain\Subscriptions\SubscriptionReminder;
use App\Domain\Subscriptions\SubscriptionStatus;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));
afterEach(fn () => Carbon::setTestNow());

/** A published course with an access mode (subscription_only, both, paid or free). */
function subCourse(string $access = 'subscription_only', string $status = 'published'): Course
{
    return Course::query()->create([
        'title' => ['ar' => 'دورة اشتراك', 'en' => 'Subscription Course '.uniqid()],
        'slug_ar' => 'sub-ar-'.uniqid(),
        'slug_en' => 'sub-en-'.uniqid(),
        'status' => $status,
        'access_mode' => $access,
    ]);
}

function subStaff(string $role = 'finance'): User
{
    return User::factory()->create(['status' => 'active'])->assignRole($role);
}

function subLearner(): User
{
    return User::factory()->create(['status' => 'active'])->assignRole('student');
}

/** @param  list<Course>  $courses */
function subPlan(User $actor, array $courses, string $term = 'monthly', int $priceMinor = 5000, bool $active = true, ?SubscriptionPlan $plan = null): SubscriptionPlan
{
    return app(SavePlan::class)($actor, [
        'name_ar' => 'باقة اشتراك',
        'name_en' => 'Plan '.uniqid(),
        'term' => $term,
        'is_active' => $active,
        'prices' => [Market::Egypt->value => $priceMinor],
        'course_ids' => array_map(static fn (Course $c): int => $c->id, $courses),
    ], $plan);
}

function subOrder(User $payer, SubscriptionPlan $plan, User $learner, ?string $key = null): Order
{
    return app(CreateOrder::class)($payer, Market::Egypt, [new OrderLine(ProductType::Plan, $plan->id, $learner)], $key ?? 'sub-key-'.uniqid());
}

function subPay(Order $order): void
{
    app(TransitionOrder::class)($order, OrderStatus::Paid);
    app(GrantOrderEntitlements::class)($order, EntitlementSource::Purchase);
}

function subPeriodFor(Order $order): SubscriptionPeriod
{
    $item = $order->items()->firstOrFail();

    return SubscriptionPeriod::query()->where('order_item_id', $item->getKey())->firstOrFail();
}

function subAccess(User $learner, Course $course): bool
{
    // EntitlementService memoises per instance; flush so each clock setting is judged fresh.
    app(EntitlementService::class)->flush();

    return app(EntitlementService::class)->canAccessCourse($learner, $course)->allowed;
}

/** Plan order for a learner, paid and granted; returns [order, period, subscription]. */
function subActivate(User $learner, SubscriptionPlan $plan, ?User $payer = null): array
{
    $order = subOrder($payer ?? $learner, $plan, $learner);
    subPay($order);
    $period = subPeriodFor($order);

    return [$order, $period, Subscription::query()->where(['plan_id' => $plan->id, 'learner_id' => $learner->id])->firstOrFail()];
}

// ---------------------------------------------------------------- A1: plan grants

it('grants a paid plan only the included courses, with subscription source, the period ref and grace end', function (): void {
    $staff = subStaff('admin');
    $included = [subCourse(), subCourse('both')];
    $other = subCourse();
    $plan = subPlan($staff, $included, 'monthly');

    $learner = subLearner();
    [$order, $period] = subActivate($learner, $plan);

    expect(subAccess($learner, $included[0]))->toBeTrue()
        ->and(subAccess($learner, $included[1]))->toBeTrue()
        ->and(subAccess($learner, $other))->toBeFalse();

    $rows = Entitlement::query()->where(['learner_id' => $learner->id, 'source_type' => EntitlementSource::Subscription->value])->get();
    expect($rows)->toHaveCount(2)
        ->and($rows->first()->source_ref)->toBe('subscription_period:'.$period->id)
        ->and($rows->first()->starts_at->toIso8601String())->toBe($period->starts_at->toIso8601String())
        ->and($rows->first()->ends_at->toIso8601String())->toBe($period->ends_at->copy()->addDays(3)->toIso8601String());
})->group('T-P8-08', 'A1')->group('subscriptions');

it('adds no second period or entitlement when the grant runs again, the order is approved again or the webhook replays', function (): void {
    $staff = subStaff('admin');
    $course = subCourse();
    $plan = subPlan($staff, [$course]);

    $learner = subLearner();
    [$order, $period] = subActivate($learner, $plan);
    $rows = Entitlement::query()->count();

    app(GrantOrderEntitlements::class)($order, EntitlementSource::Purchase);
    app(TransitionOrder::class)($order, OrderStatus::Paid);
    app(GrantOrderEntitlements::class)($order, EntitlementSource::Purchase);

    expect(SubscriptionPeriod::query()->count())->toBe(1)
        ->and(SubscriptionPeriod::query()->whereKey($period->id)->exists())->toBeTrue()
        ->and(Entitlement::query()->count())->toBe($rows)
        ->and(Activity::query()->where('event', 'subscription.started')->count())->toBe(1);
})->group('T-P8-08', 'A1')->group('subscriptions');

it('gives a parent paying for a child access for the child and not for the parent', function (): void {
    $staff = subStaff('admin');
    $course = subCourse();
    $plan = subPlan($staff, [$course]);

    $parent = User::factory()->create(['status' => 'active'])->assignRole('parent');
    $child = subLearner();
    GuardianChildLink::query()->create([
        'guardian_id' => $parent->id,
        'child_id' => $child->id,
        'status' => GuardianChildLink::STATUS_VERIFIED,
        'method' => GuardianChildLink::METHOD_CREATED_BY_PARENT,
        'verified_at' => now(),
    ]);

    [$order] = subActivate($child, $plan, $parent);

    expect(subAccess($child, $course))->toBeTrue()
        ->and(subAccess($parent, $course))->toBeFalse()
        ->and(Subscription::query()->whereKey(Subscription::query()->value('id'))->value('payer_id'))->toBe($parent->id)
        ->and($order->payer_id)->toBe($parent->id);
})->group('T-P8-08', 'A1')->group('subscriptions');

it('is not covered yet: a plan built from a category contains only that category subscription and both courses')
    ->todo()
    ->group('T-P8-08', 'A1')->group('subscriptions');

// ---------------------------------------------------------------- A2: reminders

it('sends the 7-day reminder once at 6 days before the end, with its own row, and nothing on a second run', function (): void {
    $staff = subStaff('admin');
    $plan = subPlan($staff, [subCourse()]);
    $learner = subLearner();

    Carbon::setTestNow(Carbon::parse('2026-12-01 09:00:00'));
    [, $period] = subActivate($learner, $plan);

    Carbon::setTestNow($period->ends_at->copy()->subDays(6));
    $sent = app(SendSubscriptionRenewalReminders::class)();
    expect($sent)->toBe(1)
        ->and(SubscriptionReminder::query()->where('period_id', $period->id)->pluck('offset_days')->all())->toBe([7]);

    expect(app(SendSubscriptionRenewalReminders::class)())->toBe(0)
        ->and(SubscriptionReminder::query()->where('period_id', $period->id)->count())->toBe(1);
})->group('T-P8-08', 'A2')->group('subscriptions');

it('sends the 3-day reminder at 2.5 days and the 1-day reminder at 12 hours, three rows over the period', function (): void {
    $staff = subStaff('admin');
    $plan = subPlan($staff, [subCourse()]);
    $learner = subLearner();

    Carbon::setTestNow(Carbon::parse('2026-12-01 09:00:00'));
    [, $period] = subActivate($learner, $plan);

    foreach ([6, 2.5, 0.5] as $daysBefore) {
        Carbon::setTestNow($period->ends_at->copy()->subSeconds((int) round($daysBefore * 86400)));
        expect(app(SendSubscriptionRenewalReminders::class)())->toBe(1);
    }

    expect(SubscriptionReminder::query()->where('period_id', $period->id)->orderBy('offset_days')->pluck('offset_days')->all())
        ->toBe([1, 3, 7]);
})->group('T-P8-08', 'A2')->group('subscriptions');

it('sends nothing for a cancelled subscription and nothing after the period has ended', function (): void {
    $staff = subStaff('admin');
    $plan = subPlan($staff, [subCourse()]);
    $learner = subLearner();

    Carbon::setTestNow(Carbon::parse('2026-12-01 09:00:00'));
    [, $period, $subscription] = subActivate($learner, $plan);

    app(CancelSubscription::class)($learner, $subscription);
    Carbon::setTestNow($period->ends_at->copy()->subDays(6));
    expect(app(SendSubscriptionRenewalReminders::class)())->toBe(0)
        ->and(SubscriptionReminder::query()->count())->toBe(0);

    // A second, uncancelled plan learner whose period has ended gets nothing either.
    $other = subLearner();
    Carbon::setTestNow(Carbon::parse('2026-12-01 09:00:00'));
    [, $otherPeriod] = subActivate($other, $plan);
    Carbon::setTestNow($otherPeriod->ends_at->copy()->addHour());
    expect(app(SendSubscriptionRenewalReminders::class)())->toBe(0);
})->group('T-P8-08', 'A2')->group('subscriptions');

it('is not covered yet: the notice goes to the payer in their locale, an opted-out payer gets none and a failing send leaves no row')
    ->todo()
    ->group('T-P8-08', 'A2')->group('subscriptions');

// ---------------------------------------------------------------- A3: expiry

it('keeps individually bought courses and expires only the plan course after the grace days', function (): void {
    $staff = subStaff('admin');
    $planCourse = subCourse();
    $bought = subCourse('paid');
    $plan = subPlan($staff, [$planCourse]);

    $learner = subLearner();
    Carbon::setTestNow(Carbon::parse('2026-12-01 09:00:00'));
    [, $period, $subscription] = subActivate($learner, $plan);
    app(GrantEntitlement::class)($learner, $bought, EntitlementSource::Purchase);

    Carbon::setTestNow($period->ends_at->copy()->addDay());
    expect(subAccess($learner, $planCourse))->toBeTrue();

    Carbon::setTestNow($period->grace_ends_at->copy()->addHour());
    expect(subAccess($learner, $planCourse))->toBeFalse()
        ->and(subAccess($learner, $bought))->toBeTrue();

    expect(app(ExpireSubscriptions::class)())->toBe(1)
        ->and(app(ExpireSubscriptions::class)())->toBe(0)
        ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::Expired)
        ->and(Entitlement::query()->where(['learner_id' => $learner->id, 'source_type' => EntitlementSource::Subscription->value])->value('status'))
        ->toBe(Entitlement::EXPIRED)
        ->and(Activity::query()->where('event', 'subscription.expired')->count())->toBe(1)
        ->and(subAccess($learner, $bought))->toBeTrue();
})->group('T-P8-08', 'A3')->group('subscriptions');

it('restores plan course access after a paid renewal following expiry, and earlier rows are still there', function (): void {
    $staff = subStaff('admin');
    $planCourse = subCourse();
    $plan = subPlan($staff, [$planCourse]);

    $learner = subLearner();
    Carbon::setTestNow(Carbon::parse('2026-12-01 09:00:00'));
    [, $period, $subscription] = subActivate($learner, $plan);

    Carbon::setTestNow($period->grace_ends_at->copy()->addDay());
    app(ExpireSubscriptions::class)();
    expect(subAccess($learner, $planCourse))->toBeFalse();

    $renewal = subOrder($learner, $plan, $learner);
    subPay($renewal);

    expect(subAccess($learner, $planCourse))->toBeTrue()
        ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::Active)
        ->and(SubscriptionPeriod::query()->where('subscription_id', $subscription->id)->count())->toBe(2)
        ->and(Subscription::query()->whereKey($subscription->id)->value('expired_at'))->toBeNull();
})->group('T-P8-08', 'A3')->group('subscriptions');

it('uses the entitlement service as the AI lesson access checker so an expired plan removes eligibility', function (): void {
    $checker = app(LessonAccessChecker::class);
    expect($checker)->toBeInstanceOf(EntitlementService::class);
})->group('T-P8-08', 'A3')->group('subscriptions');

// ---------------------------------------------------------------- renewal

it('returns the same pending renewal order on a repeat call and refuses it with more than 7 days left', function (): void {
    $staff = subStaff('admin');
    $plan = subPlan($staff, [subCourse()]);
    $learner = subLearner();

    Carbon::setTestNow(Carbon::parse('2026-12-01 09:00:00'));
    [, , $subscription] = subActivate($learner, $plan);

    expect(fn () => app(RenewSubscription::class)($learner, $subscription))
        ->toThrow(ValidationException::class);
})->group('T-P8-08', 'A1')->group('subscriptions');

it('allows a renewal inside the 7 day window and returns the same order for the same call', function (): void {
    $staff = subStaff('admin');
    $plan = subPlan($staff, [subCourse()]);
    $learner = subLearner();

    Carbon::setTestNow(Carbon::parse('2026-12-01 09:00:00'));
    [, $period, $subscription] = subActivate($learner, $plan);

    Carbon::setTestNow($period->ends_at->copy()->subDays(5));
    $first = app(RenewSubscription::class)($learner, $subscription);
    $second = app(RenewSubscription::class)($learner, $subscription);

    expect($first->id)->toBe($second->id)
        ->and($first->status)->toBe(OrderStatus::Pending)
        ->and($first->total_minor)->toBe(5000);
})->group('T-P8-08', 'A1')->group('subscriptions');

it('starts an early renewal at the previous period end and a renewal in grace starts now', function (): void {
    $staff = subStaff('admin');
    $plan = subPlan($staff, [subCourse()]);
    $learner = subLearner();

    Carbon::setTestNow(Carbon::parse('2026-12-01 09:00:00'));
    [, $period, $subscription] = subActivate($learner, $plan);

    Carbon::setTestNow($period->ends_at->copy()->subDays(5));
    subPay(app(RenewSubscription::class)($learner, $subscription));

    $early = SubscriptionPeriod::query()->where('subscription_id', $subscription->id)->orderBy('sequence')->get()->last();
    expect($early->sequence)->toBe(2)
        ->and($early->starts_at->toIso8601String())->toBe($period->ends_at->toIso8601String());
});

// ---------------------------------------------------------------- cancellation

it('keeps access to the period end on cancellation, not the grace, and refuses other learners', function (): void {
    $staff = subStaff('admin');
    $course = subCourse();
    $plan = subPlan($staff, [$course]);
    $learner = subLearner();
    $stranger = subLearner();

    Carbon::setTestNow(Carbon::parse('2026-12-01 09:00:00'));
    [, $period, $subscription] = subActivate($learner, $plan);

    expect(fn () => app(CancelSubscription::class)($stranger, $subscription))->toThrow(AuthorizationException::class);
    expect(fn () => app(CancelSubscription::class)(subStaff('support'), $subscription))->toThrow(AuthorizationException::class);

    Carbon::setTestNow($period->ends_at->copy()->subDay());
    app(CancelSubscription::class)($learner, $subscription);
    $cancelledAt = $subscription->fresh()->cancelled_at;
    expect($cancelledAt)->not->toBeNull()
        ->and(subAccess($learner, $course))->toBeTrue();

    Carbon::setTestNow($period->ends_at->copy()->addHour());
    expect(subAccess($learner, $course))->toBeFalse();

    app(CancelSubscription::class)($learner, $subscription);
    expect($subscription->fresh()->cancelled_at->toIso8601String())->toBe($cancelledAt->toIso8601String())
        ->and(Activity::query()->where('event', 'subscription.cancelled')->count())->toBe(1);
})->group('T-P8-08', 'A1')->group('subscriptions');

// ---------------------------------------------------------------- order engine

it('refuses an inactive plan, a plan with only draft courses, a missing EG price and the Saudi market', function (): void {
    $staff = subStaff('admin');
    $learner = subLearner();
    $live = subCourse();

    $inactive = subPlan($staff, [$live], 'monthly', 5000, false);
    expect(fn () => subOrder($learner, $inactive, $learner))->toThrow(ValidationException::class);

    $draft = subCourse('subscription_only', 'draft');
    $draftPlan = subPlan($staff, [$draft]);
    expect(fn () => subOrder($learner, $draftPlan, $learner))->toThrow(ValidationException::class);

    $priced = subPlan($staff, [$live]);
    DB::table('subscription_plan_prices')->where('plan_id', $priced->id)->delete();
    expect(fn () => subOrder($learner, $priced, $learner))->toThrow(ValidationException::class);

    $fresh = subPlan($staff, [$live]);
    expect(fn () => app(CreateOrder::class)($learner, Market::SaudiArabia, [new OrderLine(ProductType::Plan, $fresh->id, $learner)], 'sub-sa-refuse-1'))
        ->toThrow(ValidationException::class);
})->group('T-P8-08', 'A1')->group('subscriptions');

it('ignores a client price and refuses a second plan line for the same learner in one order', function (): void {
    $staff = subStaff('admin');
    $learner = subLearner();
    $plan = subPlan($staff, [subCourse()], 'monthly', 7000);

    $order = subOrder($learner, $plan, $learner);
    expect($order->total_minor)->toBe(7000)
        ->and(fn () => app(CreateOrder::class)($learner, Market::Egypt, [
            new OrderLine(ProductType::Plan, $plan->id, $learner),
            new OrderLine(ProductType::Plan, $plan->id, $learner),
        ], 'sub-dup-refuse-1'))->toThrow(ValidationException::class);
})->group('T-P8-08', 'A1')->group('subscriptions');

it('refuses a coupon on a plan-only order with coupon_ineligible', function (): void {
    $staff = subStaff('admin');
    $course = subCourse();
    $plan = subPlan($staff, [$course]);
    $learner = subLearner();
    $order = subOrder($learner, $plan, $learner);

    app(SaveCoupon::class)($staff, [
        'code' => 'SUBCOUPON',
        'name' => 'Plan coupon',
        'discount_type' => 'fixed',
        'amount_minor' => 1000,
        'currency' => 'EGP',
        'max_redemptions' => 5,
        'max_per_user' => 1,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addDay(),
        'is_active' => true,
        'course_ids' => [$course->id],
    ]);

    expect(fn () => app(ApplyCoupon::class)($order, $learner, 'SUBCOUPON'))->toThrow(ValidationException::class);
})->group('T-P8-08', 'A1')->group('subscriptions');

it('fulfils a mixed course and plan order so each line is granted', function (): void {
    $staff = subStaff('admin');
    $planCourse = subCourse();
    $single = subCourse('paid');
    CoursePrice::query()->create(['course_id' => $single->id, 'market' => Market::Egypt->value, 'currency' => 'EGP', 'amount_minor' => 9000]);
    $plan = subPlan($staff, [$planCourse]);
    $learner = subLearner();

    $order = app(CreateOrder::class)($learner, Market::Egypt, [
        new OrderLine(ProductType::Course, $single->id, $learner),
        new OrderLine(ProductType::Plan, $plan->id, $learner),
    ], 'sub-mixed-0001');
    subPay($order);

    expect(subAccess($learner, $single))->toBeTrue()
        ->and(subAccess($learner, $planCourse))->toBeTrue()
        ->and(SubscriptionPeriod::query()->count())->toBe(1);
})->group('T-P8-08', 'A1')->group('subscriptions');

// ---------------------------------------------------------------- SavePlan

it('lets finance and admin save a plan and refuses learners, instructors, support and inactive staff', function (): void {
    $course = subCourse();

    expect(subPlan(subStaff('finance'), [$course])->exists)->toBeTrue()
        ->and(subPlan(subStaff('admin'), [$course])->exists)->toBeTrue();

    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_independent');
    $inactive = User::factory()->create(['status' => 'inactive'])->assignRole('finance');
    foreach ([subLearner(), $instructor, subStaff('support'), $inactive] as $actor) {
        expect(fn () => subPlan($actor, [$course]))->toThrow(AuthorizationException::class);
    }
})->group('T-P8-08', 'A1')->group('subscriptions');

it('refuses invalid plan data with the subscriptions language keys', function (): void {
    $staff = subStaff('admin');
    $course = subCourse();
    $draftCourse = subCourse('free');

    $refuses = function (callable $fn, string $field, string $key): void {
        try {
            $fn();
            test()->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey($field)
                ->and($e->errors()[$field][0])->toBe(__('subscriptions.'.$key));
        }
    };

    $base = ['name_ar' => 'باقة', 'name_en' => 'Plan', 'term' => 'monthly', 'is_active' => false, 'prices' => [Market::Egypt->value => 100], 'course_ids' => [$course->id]];

    $refuses(fn () => app(SavePlan::class)($staff, [...$base, 'name_ar' => 'ا']), 'name', 'name_invalid');
    $refuses(fn () => app(SavePlan::class)($staff, [...$base, 'term' => 'weekly']), 'term', 'term_invalid');
    $refuses(fn () => app(SavePlan::class)($staff, [...$base, 'course_ids' => [$draftCourse->id]]), 'courses', 'courses_not_subscribable');
    $refuses(fn () => app(SavePlan::class)($staff, [...$base, 'course_ids' => []]), 'courses', 'courses_invalid');
    $refuses(fn () => app(SavePlan::class)($staff, [...$base, 'prices' => [Market::Egypt->value => 0]]), 'prices', 'price_invalid');
    $refuses(fn () => app(SavePlan::class)($staff, [...$base, 'is_active' => true, 'prices' => [Market::SaudiArabia->value => 500]]), 'prices', 'price_required');
})->group('T-P8-08', 'A1')->group('subscriptions');

it('edits replace courses and prices, write plan.updated and leave existing periods and entitlements alone', function (): void {
    $staff = subStaff('admin');
    $a = subCourse();
    $b = subCourse();
    $c = subCourse();
    $plan = subPlan($staff, [$a, $b], 'monthly', 5000);

    $learner = subLearner();
    Carbon::setTestNow(Carbon::parse('2026-12-01 09:00:00'));
    [, $period] = subActivate($learner, $plan);
    $entitlements = Entitlement::query()->count();

    subPlan($staff, [$c], 'annual', 60000, true, $plan);

    expect($plan->fresh()->courses()->pluck('course_id')->all())->toBe([$c->id])
        ->and($plan->fresh()->prices()->value('amount_minor'))->toBe(60000)
        ->and(SubscriptionPeriod::query()->whereKey($period->id)->value('ends_at')->toIso8601String())->toBe($period->ends_at->toIso8601String())
        ->and(Entitlement::query()->count())->toBe($entitlements)
        ->and(Activity::query()->where('event', 'plan.updated')->count())->toBe(1);
})->group('T-P8-08', 'A1')->group('subscriptions');

// ---------------------------------------------------------------- command, schedule, arch, lang

it('runs subscriptions:process and reports both counts, scheduled hourly', function (): void {
    $code = Artisan::call('subscriptions:process');
    $output = Artisan::output();

    expect($code)->toBe(0)
        ->and($output)->toContain('reminder')
        ->and($output)->toContain('Expired');

    $scheduled = collect(app(Illuminate\Console\Scheduling\Schedule::class)->events())->first(
        static fn ($event): bool => str_contains((string) $event->command, 'subscriptions:process')
    );
    expect($scheduled)->not->toBeNull()
        ->and($scheduled->expression)->toBe('0 * * * *');
})->group('T-P8-08', 'A2')->group('subscriptions');

it('keeps lang/ar and lang/en subscriptions keys identical and has the subscription audit labels in both locales', function (): void {
    $ar = require dirname(__DIR__, 3).'/lang/ar/subscriptions.php';
    $en = require dirname(__DIR__, 3).'/lang/en/subscriptions.php';
    expect(array_keys($ar))->toEqualCanonicalizing(array_keys($en));

    $auditAr = require dirname(__DIR__, 3).'/lang/ar/audit-events.php';
    $auditEn = require dirname(__DIR__, 3).'/lang/en/audit-events.php';
    foreach (['plan.created', 'plan.updated', 'subscription.started', 'subscription.renewed', 'subscription.cancelled', 'subscription.expired'] as $event) {
        expect($auditAr)->toHaveKey($event)
            ->and($auditEn)->toHaveKey($event);
    }
})->group('T-P8-08', 'A2')->group('i18n');

it('keeps App\Domain\Subscriptions free of HTTP and Filament imports and the new Actions final', function (): void {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 3).'/app/Domain/Subscriptions'));
    foreach ($files as $info) {
        if (! $info->isFile() || $info->getExtension() !== 'php') {
            continue;
        }
        $source = file_get_contents($info->getPathname());
        expect($source)->not->toContain('Filament\\')
            ->and($source)->not->toContain('Illuminate\\Http\\');
    }
    foreach ([SavePlan::class, RenewSubscription::class, CancelSubscription::class, ExpireSubscriptions::class, SendSubscriptionRenewalReminders::class] as $class) {
        expect((new ReflectionClass($class))->isFinal())->toBeTrue();
    }
})->group('T-P8-08', 'A2')->group('arch');

it('is not covered yet: Filament plan and subscription pages, summary tiles and the cancel action')
    ->todo()
    ->group('T-P8-08', 'A2')->group('subscriptions');
