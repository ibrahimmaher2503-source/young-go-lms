# Subscriptions, Bundles & Admin Access Editing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Enable administrators to edit course access grants and learner subscription period dates from Filament, and surface active subscription plans and course bundles across the public course detail, catalog, and plans pages.

**Architecture:** Add `UpdateEntitlement` and `UpdateSourceEntitlementDates` in `App\Domain\Entitlements\Actions` (preserving Invariant `I01`) and `UpdateSubscriptionPeriod` in `App\Domain\Subscriptions\Actions`, wired to modal edit actions on `ManualGrantResource` and `SubscriptionResource`. Extend `CheckoutOffers` with per-course plan and bundle lookups (`plansForCourse`, `bundlesForCourse`) and render subscription/bundle offers on `catalog/show.blade.php`, `catalog/_cta.blade.php`, `catalog/plans.blade.php`, and `catalog/index.blade.php`.

**Tech Stack:** PHP 8.4, Laravel 13, Filament 5, Blade, Tailwind CSS (design tokens & logical RTL utilities), Pest.

**Spec:** `docs/superpowers/specs/2026-11-21-subscriptions-bundles-admin-edit-design.md`

## Global Constraints

- Business logic only in `app/Domain/*/Actions` (`final` classes, `declare(strict_types=1);`, no `Illuminate\Http` or `App\Http` imports in `App\Domain`).
- Only `App\Domain\Entitlements` may write to the `entitlements` table (`I01`).
- Every new audit event must have labels in both `lang/en/audit-events.php` and `lang/ar/audit-events.php`.
- Every user-facing translation key must exist in both `lang/en/*.php` and `lang/ar/*.php` (`php artisan app:check-translations`).
- Only design tokens and logical directional CSS classes in Blade views (`php artisan app:check-rtl`).

## Review Focus

- Attempting to edit a revoked entitlement must be rejected with a `ValidationException` (`entitlements.cannot_edit_revoked`) and leave the record unchanged.
- Setting `ends_at` earlier than or equal to `starts_at` on `UpdateEntitlement` or `UpdateSubscriptionPeriod` must be rejected with `ValidationException` (`access.ends_before_start`).
- Unauthorized users (learners, instructors, or inactive staff) calling `UpdateEntitlement` or `UpdateSubscriptionPeriod` must receive `AuthorizationException`.
- Extending an expired or cancelled subscription period into the future via `UpdateSubscriptionPeriod` must reactivate the `Subscription` (`status = active`, `cancelled_at = null`, `expired_at = null`) and update its non-revoked period entitlements so `EntitlementService::canAccessCourse()` immediately allows access.
- A `subscription`-mode course with an active sellable plan must show `data-cta="subscribe"` linking to `/plans` for signed-in non-entitled learners (while an unpriced `paid` course with no plan still shows `data-cta="soon"`).

---

### Task 1: Admin Editing of Course Access Grants (`UpdateEntitlement` & `ManualGrantResource`)

**Files:**
- Create: `app/Domain/Entitlements/Actions/UpdateEntitlement.php`
- Create: `app/Domain/Entitlements/Actions/UpdateSourceEntitlementDates.php`
- Modify: `app/Filament/Resources/ManualGrants/ManualGrantResource.php`
- Modify: `lang/en/entitlements.php`
- Modify: `lang/ar/entitlements.php`
- Modify: `lang/en/audit-events.php`
- Modify: `lang/ar/audit-events.php`
- Test: `tests/Feature/Entitlements/UpdateEntitlementTest.php`

**Interfaces:**
- Produces:
  - `App\Domain\Entitlements\Actions\UpdateEntitlement::__invoke(User $actor, Entitlement $entitlement, CarbonInterface $startsAt, ?CarbonInterface $endsAt, string $reason): Entitlement`
  - `App\Domain\Entitlements\Actions\UpdateSourceEntitlementDates::__invoke(User $actor, EntitlementSource $source, string $sourceRef, CarbonInterface $startsAt, ?CarbonInterface $endsAt, string $reason): int`

- [ ] **Step 1: Write the failing test in `tests/Feature/Entitlements/UpdateEntitlementTest.php`**

```php
<?php

declare(strict_types=1);

use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\Actions\RevokeEntitlement;
use App\Domain\Entitlements\Actions\UpdateEntitlement;
use App\Domain\Entitlements\Actions\UpdateSourceEntitlementDates;
use App\Domain\Entitlements\Entitlement;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Entitlements\EntitlementSource;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Carbon::setTestNow('2026-11-21 10:00:00');
});

afterEach(fn () => Carbon::setTestNow());

it('allows staff with manual_entitlement.grant to update starts_at, ends_at, lifetime and reason and records entitlement.updated audit', function (): void {
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $learner = User::factory()->create(['status' => 'active'])->assignRole('student');
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة', 'en' => 'Course'],
        'slug_ar' => 'c-ar-1', 'slug_en' => 'c-en-1', 'status' => 'published', 'access_mode' => 'paid', 'manual_access_allowed' => true,
    ]);

    $grant = app(GrantEntitlement::class)($learner, $course, EntitlementSource::ManualGrant, 'manual-1', now()->subDays(10), now()->subDay(), $admin, 'Initial grant');
    expect(app(EntitlementService::class)->canAccessCourse($learner, $course)->allowed)->toBeFalse();

    $updated = app(UpdateEntitlement::class)($admin, $grant, now()->subDays(5), now()->addDays(30), 'Extended for learner');
    expect($updated->status)->toBe(Entitlement::ACTIVE)
        ->and($updated->ends_at?->toDateString())->toBe(now()->addDays(30)->toDateString())
        ->and($updated->reason)->toBe('Extended for learner')
        ->and(app(EntitlementService::class)->canAccessCourse($learner, $course)->allowed)->toBeTrue()
        ->and(Activity::query()->where('event', 'entitlement.updated')->count())->toBe(1);

    $lifetime = app(UpdateEntitlement::class)($admin, $updated, now()->subDays(5), null, 'Converted to lifetime');
    expect($lifetime->ends_at)->toBeNull();
});

it('rejects editing a revoked entitlement, invalid dates or unauthorized actors', function (): void {
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $learner = User::factory()->create(['status' => 'active'])->assignRole('student');
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة 2', 'en' => 'Course 2'],
        'slug_ar' => 'c-ar-2', 'slug_en' => 'c-en-2', 'status' => 'published', 'access_mode' => 'paid', 'manual_access_allowed' => true,
    ]);
    $grant = app(GrantEntitlement::class)($learner, $course, EntitlementSource::ManualGrant, 'manual-2', now(), now()->addDays(5), $admin, 'Initial');

    expect(fn () => app(UpdateEntitlement::class)($learner, $grant, now(), now()->addDays(10), 'Try hack'))
        ->toThrow(AuthorizationException::class);

    expect(fn () => app(UpdateEntitlement::class)($admin, $grant, now(), now()->subDay(), 'Bad end date'))
        ->toThrow(ValidationException::class);

    app(RevokeEntitlement::class)($admin, $grant, 'Revoked now');
    expect(fn () => app(UpdateEntitlement::class)($admin, $grant->refresh(), now(), now()->addDays(10), 'Reopen'))
        ->toThrow(ValidationException::class);
});

it('updates all non-revoked entitlements for a given source reference', function (): void {
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $learner = User::factory()->create(['status' => 'active'])->assignRole('student');
    $c1 = Course::query()->create(['title' => ['ar' => '١', 'en' => 'C1'], 'slug_ar' => 's1-ar', 'slug_en' => 's1-en', 'status' => 'published', 'access_mode' => 'subscription']);
    $c2 = Course::query()->create(['title' => ['ar' => '٢', 'en' => 'C2'], 'slug_ar' => 's2-ar', 'slug_en' => 's2-en', 'status' => 'published', 'access_mode' => 'subscription']);

    app(GrantEntitlement::class)($learner, $c1, EntitlementSource::Subscription, 'subscription_period:99', now()->subMonth(), now()->subDay(), $admin);
    app(GrantEntitlement::class)($learner, $c2, EntitlementSource::Subscription, 'subscription_period:99', now()->subMonth(), now()->subDay(), $admin);

    $count = app(UpdateSourceEntitlementDates::class)($admin, EntitlementSource::Subscription, 'subscription_period:99', now()->subMonth(), now()->addMonth(), 'Extended period');
    expect($count)->toBe(2)
        ->and(app(EntitlementService::class)->canAccessCourse($learner, $c1)->allowed)->toBeTrue()
        ->and(app(EntitlementService::class)->canAccessCourse($learner, $c2)->allowed)->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Entitlements/UpdateEntitlementTest.php`
Expected: FAIL with `Class "App\Domain\Entitlements\Actions\UpdateEntitlement" not found`

- [ ] **Step 3: Implement `UpdateEntitlement`, `UpdateSourceEntitlementDates`, translations, and `ManualGrantResource` edit action**

Add `entitlement.updated` to `lang/en/audit-events.php` and `lang/ar/audit-events.php`.
Add `edit`, `updated`, and `cannot_edit_revoked` to `lang/en/entitlements.php` and `lang/ar/entitlements.php`.
Implement `App\Domain\Entitlements\Actions\UpdateEntitlement` and `App\Domain\Entitlements\Actions\UpdateSourceEntitlementDates`.
Add the `edit` recordAction in `ManualGrantResource::table()` (visible when `$r->status !== Entitlement::REVOKED`).

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Entitlements/UpdateEntitlementTest.php`
Expected: PASS (3 passed)

---

### Task 2: Admin Editing of Learner Subscriptions (`UpdateSubscriptionPeriod` & `SubscriptionResource`)

**Files:**
- Create: `app/Domain/Subscriptions/Actions/UpdateSubscriptionPeriod.php`
- Modify: `app/Filament/Resources/Subscriptions/SubscriptionResource.php`
- Modify: `lang/en/subscriptions.php`
- Modify: `lang/ar/subscriptions.php`
- Modify: `lang/en/audit-events.php`
- Modify: `lang/ar/audit-events.php`
- Test: `tests/Feature/Subscriptions/UpdateSubscriptionPeriodTest.php`

**Interfaces:**
- Consumes: `App\Domain\Entitlements\Actions\UpdateSourceEntitlementDates`
- Produces: `App\Domain\Subscriptions\Actions\UpdateSubscriptionPeriod::__invoke(User $actor, Subscription $subscription, CarbonInterface $endsAt, bool $reactivate = true, ?string $reason = null): SubscriptionPeriod`

- [ ] **Step 1: Write the failing test in `tests/Feature/Subscriptions/UpdateSubscriptionPeriodTest.php`**

```php
<?php

declare(strict_types=1);

use App\Domain\Commerce\Actions\CreateOrder;
use App\Domain\Commerce\Actions\GrantOrderEntitlements;
use App\Domain\Commerce\Actions\TransitionOrder;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\ProductType;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Shared\Markets\Market;
use App\Domain\Subscriptions\Actions\CancelSubscription;
use App\Domain\Subscriptions\Actions\ExpireSubscriptions;
use App\Domain\Subscriptions\Actions\SavePlan;
use App\Domain\Subscriptions\Actions\UpdateSubscriptionPeriod;
use App\Domain\Subscriptions\Subscription;
use App\Domain\Subscriptions\SubscriptionStatus;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Carbon::setTestNow('2026-11-21 10:00:00');
});

afterEach(fn () => Carbon::setTestNow());

it('allows admin to extend or edit a subscription period end date, syncs course entitlements and reactivates expired/cancelled subscriptions', function (): void {
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $learner = User::factory()->create(['status' => 'active'])->assignRole('student');
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة اشتراك', 'en' => 'Sub Course'],
        'slug_ar' => 'sub-c-ar', 'slug_en' => 'sub-c-en', 'status' => 'published', 'access_mode' => 'subscription',
    ]);
    $plan = app(SavePlan::class)($admin, [
        'name_ar' => 'خطة شهرية', 'name_en' => 'Monthly Plan', 'term' => 'monthly', 'is_active' => true,
        'prices' => [Market::Egypt->value => 15000], 'course_ids' => [$course->id],
    ]);

    $order = app(CreateOrder::class)($learner, Market::Egypt, [new OrderLine(ProductType::Plan, $plan->id, $learner)], 'sub-edit-1');
    app(TransitionOrder::class)($order, OrderStatus::Paid);
    app(GrantOrderEntitlements::class)($order->refresh(), EntitlementSource::Purchase);

    $subscription = Subscription::query()->where('learner_id', $learner->id)->sole();
    app(CancelSubscription::class)($admin, $subscription);

    // Move past grace so it expires
    Carbon::setTestNow('2027-01-10 10:00:00');
    app(ExpireSubscriptions::class)();
    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Expired)
        ->and(app(EntitlementService::class)->canAccessCourse($learner, $course)->allowed)->toBeFalse();

    $newEndsAt = now()->addDays(20)->endOfDay();
    $period = app(UpdateSubscriptionPeriod::class)($admin, $subscription, $newEndsAt, true, 'Admin extension');

    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->cancelled_at)->toBeNull()
        ->and($subscription->expired_at)->toBeNull()
        ->and($period->ends_at->toDateString())->toBe($newEndsAt->toDateString())
        ->and($period->grace_ends_at->toDateString())->toBe($newEndsAt->copy()->addDays($plan->grace_days)->toDateString())
        ->and(app(EntitlementService::class)->canAccessCourse($learner, $course)->allowed)->toBeTrue()
        ->and(Activity::query()->where('event', 'subscription.updated')->where('subject_id', $subscription->id)->count())->toBe(1);
});

it('refuses unauthorized actors or an end date before period start', function (): void {
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $learner = User::factory()->create(['status' => 'active'])->assignRole('student');
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة ٢', 'en' => 'Sub Course 2'],
        'slug_ar' => 'sub-c2-ar', 'slug_en' => 'sub-c2-en', 'status' => 'published', 'access_mode' => 'subscription',
    ]);
    $plan = app(SavePlan::class)($admin, [
        'name_ar' => 'خطة ٢', 'name_en' => 'Plan 2', 'term' => 'monthly', 'is_active' => true,
        'prices' => [Market::Egypt->value => 15000], 'course_ids' => [$course->id],
    ]);
    $order = app(CreateOrder::class)($learner, Market::Egypt, [new OrderLine(ProductType::Plan, $plan->id, $learner)], 'sub-edit-2');
    app(TransitionOrder::class)($order, OrderStatus::Paid);
    app(GrantOrderEntitlements::class)($order->refresh(), EntitlementSource::Purchase);
    $subscription = Subscription::query()->where('learner_id', $learner->id)->sole();

    expect(fn () => app(UpdateSubscriptionPeriod::class)($learner, $subscription, now()->addMonth(), true, 'Hack'))
        ->toThrow(AuthorizationException::class);

    expect(fn () => app(UpdateSubscriptionPeriod::class)($admin, $subscription, now()->subDays(5), true, 'Before start'))
        ->toThrow(ValidationException::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Subscriptions/UpdateSubscriptionPeriodTest.php`
Expected: FAIL with `Class "App\Domain\Subscriptions\Actions\UpdateSubscriptionPeriod" not found`

- [ ] **Step 3: Implement `UpdateSubscriptionPeriod`, translations, and `SubscriptionResource` edit action**

Add `subscription.updated` to `lang/en/audit-events.php` and `lang/ar/audit-events.php`.
Add `edit_period`, `updated`, `reactivate`, and `reason` keys to `lang/en/subscriptions.php` and `lang/ar/subscriptions.php`.
Implement `App\Domain\Subscriptions\Actions\UpdateSubscriptionPeriod`.
Add the `edit` modal action in `SubscriptionResource::table()` alongside `cancel`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Subscriptions/UpdateSubscriptionPeriodTest.php`
Expected: PASS (2 passed)

---

### Task 3: Storefront Visibility for Subscription Plans & Bundles (`CheckoutOffers`, Course Detail, Catalog, Plans Page)

**Files:**
- Modify: `app/Domain/Commerce/Queries/CheckoutOffers.php`
- Modify: `app/Http/Controllers/PublicCourseController.php`
- Modify: `app/Http/Controllers/PublicPlansController.php`
- Modify: `app/Http/Controllers/PublicCatalogController.php`
- Modify: `resources/views/catalog/_cta.blade.php`
- Modify: `resources/views/catalog/show.blade.php`
- Modify: `resources/views/catalog/plans.blade.php`
- Modify: `resources/views/catalog/index.blade.php`
- Modify: `lang/en/subscriptions.php`
- Modify: `lang/ar/subscriptions.php`
- Test: `tests/Feature/Commerce/StorefrontOffersVisibilityTest.php`

**Interfaces:**
- Produces:
  - `App\Domain\Commerce\Queries\CheckoutOffers::plansForCourse(Course $course, Market $market): Collection<int, SubscriptionPlan>`
  - `App\Domain\Commerce\Queries\CheckoutOffers::bundlesForCourse(Course $course, Market $market): Collection<int, Bundle>`

- [ ] **Step 1: Write the failing test in `tests/Feature/Commerce/StorefrontOffersVisibilityTest.php`**

```php
<?php

declare(strict_types=1);

use App\Domain\Commerce\Actions\SaveBundle;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Seo\PublicUrl;
use App\Domain\Shared\Markets\Market;
use App\Domain\Subscriptions\Actions\SavePlan;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('shows subscribe CTA and plan/bundle offers on the public course page when covered by active plans or bundles', function (): void {
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $student = User::factory()->create(['status' => 'active'])->assignRole('student');

    $subCourse = Course::query()->create([
        'title' => ['ar' => 'دورة اشتراك فقط', 'en' => 'Subscription Only Course'],
        'slug_ar' => 'sub-only-ar', 'slug_en' => 'sub-only-en', 'status' => 'published', 'access_mode' => Course::ACCESS_SUBSCRIPTION,
    ]);
    $bothCourse = Course::query()->create([
        'title' => ['ar' => 'دورة مشتركة', 'en' => 'Both Mode Course'],
        'slug_ar' => 'both-ar', 'slug_en' => 'both-en', 'status' => 'published', 'access_mode' => Course::ACCESS_BOTH,
    ]);
    CoursePrice::query()->create(['course_id' => $bothCourse->id, 'market' => 'EG', 'currency' => 'EGP', 'amount_minor' => 30000]);

    $plan = app(SavePlan::class)($admin, [
        'name_ar' => 'الخطة الإبداعية', 'name_en' => 'Creative Plan', 'term' => 'monthly', 'is_active' => true,
        'prices' => [Market::Egypt->value => 19900], 'course_ids' => [$subCourse->id, $bothCourse->id],
    ]);
    $bundle = app(SaveBundle::class)($admin, [
        'name_ar' => 'باقة التمثيل والرسم', 'name_en' => 'Acting & Drawing Bundle', 'is_active' => true,
        'prices' => [Market::Egypt->value => 45000],
        'items' => [['course_id' => $subCourse->id, 'weight' => 1], ['course_id' => $bothCourse->id, 'weight' => 1]],
    ]);

    // Signed-in learner on subscription-only course sees subscribe CTA instead of disabled soon button
    $this->actingAs($student)->get(route('catalog.show', $subCourse))
        ->assertOk()
        ->assertSee('data-cta="subscribe"', false)
        ->assertSee('Creative Plan')
        ->assertSee('Acting & Drawing Bundle')
        ->assertSee(PublicUrl::bundle($bundle, 'en'), false);

    // Signed-in learner on both-mode course sees buy CTA AND plan/bundle links
    $this->actingAs($student)->get(route('catalog.show', $bothCourse))
        ->assertOk()
        ->assertSee('data-cta="buy"', false)
        ->assertSee('data-course-plans', false)
        ->assertSee('data-course-bundles', false);
});

it('lists active bundles on the public plans page and links to offers from the catalog when plans or bundles exist', function (): void {
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $c1 = Course::query()->create(['title' => ['ar' => 'دورة ١', 'en' => 'Course 1'], 'slug_ar' => 'c1-ar', 'slug_en' => 'c1-en', 'status' => 'published', 'access_mode' => Course::ACCESS_BOTH]);
    $c2 = Course::query()->create(['title' => ['ar' => 'دورة ٢', 'en' => 'Course 2'], 'slug_ar' => 'c2-ar', 'slug_en' => 'c2-en', 'status' => 'published', 'access_mode' => Course::ACCESS_BOTH]);

    app(SavePlan::class)($admin, [
        'name_ar' => 'خطة الشاملة', 'name_en' => 'All Access Plan', 'term' => 'annual', 'is_active' => true,
        'prices' => [Market::Egypt->value => 99000], 'course_ids' => [$c1->id],
    ]);
    $bundle = app(SaveBundle::class)($admin, [
        'name_ar' => 'باقة البداية', 'name_en' => 'Starter Bundle', 'is_active' => true,
        'prices' => [Market::Egypt->value => 35000],
        'items' => [['course_id' => $c1->id, 'weight' => 1], ['course_id' => $c2->id, 'weight' => 1]],
    ]);

    $this->get(route('plans.index'))
        ->assertOk()
        ->assertSee('All Access Plan')
        ->assertSee('Starter Bundle')
        ->assertSee(PublicUrl::bundle($bundle, 'en'), false);

    $this->get(route('catalog.index'))
        ->assertOk()
        ->assertSee('data-catalog-offers', false)
        ->assertSee(PublicUrl::plans('en'), false);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Commerce/StorefrontOffersVisibilityTest.php`
Expected: FAIL with missing `data-cta="subscribe"` / `plansForCourse`

- [ ] **Step 3: Implement `CheckoutOffers` methods, controller data, and storefront views**

Add `plansForCourse(Course $course, Market $market)` and `bundlesForCourse(Course $course, Market $market)` (plus `sellableBundles(Market $market)` and `sellablePlans(Market $market)`) to `CheckoutOffers`.
Update `PublicCourseController`, `PublicPlansController`, and `PublicCatalogController`.
Update `resources/views/catalog/_cta.blade.php`, `resources/views/catalog/show.blade.php`, `resources/views/catalog/plans.blade.php`, and `resources/views/catalog/index.blade.php`.
Add localized strings in `lang/en/subscriptions.php` and `lang/ar/subscriptions.php`.

- [ ] **Step 4: Run test and full quality checks to verify everything passes**

Run: `php artisan test tests/Feature/Commerce/StorefrontOffersVisibilityTest.php tests/Feature/Commerce/CheckoutUiTest.php tests/Feature/Subscriptions tests/Feature/Entitlements`
Run: `php artisan app:check-translations && php artisan app:check-rtl`
Expected: PASS with 0 errors
