<?php

declare(strict_types=1);

// T-SIDEBAR-V2 | cases 1, 2, 3, 5, 6 | StaffNavigation, HasQueueBadge, OrderResource

use App\Domain\Commerce\ManualPaymentProof;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\PaymentAttempt;
use App\Domain\Commerce\PaymentEvent;
use App\Domain\Commerce\PaymentEventOutcome;
use App\Domain\Commerce\ProductType;
use App\Domain\Entitlements\Entitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Instructors\Models\InstructorAgreement;
use App\Domain\Shared\Markets\Market;
use App\Domain\Shared\Money\PriceFormatter;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Coupons\CouponResource;
use App\Filament\Resources\Courses\CourseResource;
use App\Filament\Resources\HelpRequests\HelpRequestResource;
use App\Filament\Resources\ManualPayments\ManualPaymentProofResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Refunds\RefundResource;
use App\Filament\Resources\Reviews\CourseReviewResource;
use App\Filament\Resources\SubmissionReview\SubmissionReviewResource;
use App\Filament\Shared\StaffNavigation;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Cache::flush();
});

it('orders the sidebar groups per panel as the catalog says', function (): void {
    expect(array_slice(StaffNavigation::order('admin'), 0, 4))->toBe(['reviews', 'inbox', 'courses', 'sales'])
        ->and(array_slice(StaffNavigation::order('finance'), 0, 2))->toBe(['sales', 'finance'])
        ->and(array_slice(StaffNavigation::order('instructor'), 0, 3))->toBe(['courses', 'reviews', 'inbox']);

    $admin = StaffNavigation::order('admin');
    expect(array_search('people', $admin, true))->toBeGreaterThan(array_search('courses', $admin, true))
        ->and(array_search('settings', $admin, true))->toBeGreaterThan(array_search('reports', $admin, true));
})->group('T-SIDEBAR-V2', 'case-1');

it('shows dashboard then the authorized new-course shortcut', function (string $panel, string $role, bool $canCreate): void {
    $this->actingAs(User::factory()->create(['status' => 'active'])->assignRole($role));
    Filament::setCurrentPanel(Filament::getPanel($panel));
    $groups = Filament::getNavigation();
    $firstItems = array_values($groups[0]->getItems());
    expect($firstItems[0]->getUrl())->toBe(url('/'.$panel));
    $routes = collect($groups)->flatMap(fn ($group): array => $group->getItems())->map(fn ($item): string => $item->getUrl());
    expect($routes->filter(fn (string $url): bool => $url === url("/{$panel}/courses/create"))->count())->toBe((int) $canCreate)
        ->and($routes->duplicates()->all())->toBe([]);
    if ($canCreate) {
        expect($firstItems[1]->getLabel())->toBe(__('courses.new_course'));
    }
})->with([
    ['admin', 'admin', true],
    ['instructor', 'instructor_academy', true],
    ['admin', 'marketing', false],
    ['finance', 'finance', false],
])->group('T-SIDEBAR-V2', 'T-P9-09', 'A1');

it('keeps creation unavailable to an independent instructor who has not accepted the active agreement', function (): void {
    InstructorAgreement::query()->create([
        'version' => 'sidebar-v1',
        'title' => ['ar' => 'اتفاقية المدرب', 'en' => 'Instructor agreement'],
        'body' => ['ar' => 'اتفاقية اختبار', 'en' => 'Test agreement'],
        'status' => InstructorAgreement::PUBLISHED,
        'published_at' => now(),
    ]);
    $this->actingAs(User::factory()->create(['status' => 'active'])->assignRole('instructor_independent'));
    Filament::setCurrentPanel(Filament::getPanel('instructor'));

    $items = Filament::getNavigation()[0]->getItems();
    expect(array_map(fn ($item): string => $item->getUrl(), $items))->toBe([url('/instructor')]);
    $this->get('/instructor/courses/create')->assertForbidden();
})->group('T-SIDEBAR-V2', 'T-P9-09', 'A1', 'YG-GAP-INS-002');

it('keeps the item order inside each group', function (): void {
    $sales = StaffNavigation::GROUPS['sales'];
    expect(array_search(OrderResource::class, $sales, true))
        ->toBeLessThan(array_search(ManualPaymentProofResource::class, $sales, true))
        ->toBeLessThan(array_search(RefundResource::class, $sales, true))
        ->toBeLessThan(array_search(CouponResource::class, $sales, true));

    $courses = StaffNavigation::GROUPS['courses'];
    expect(array_search(CourseResource::class, $courses, true))->toBeLessThan(array_search(CategoryResource::class, $courses, true));

    expect(StaffNavigation::GROUPS['inbox'][0])->toBe(HelpRequestResource::class);

    expect(StaffNavigation::GROUPS['reviews'])->toContain(CourseReviewResource::class, SubmissionReviewResource::class)
        ->and($courses)->not->toContain(SubmissionReviewResource::class);
})->group('T-SIDEBAR-V2', 'T-P9-09', 'A1', 'case-2');

it('puts transfer proofs and refunds before order history in the finance sidebar', function (): void {
    $this->actingAs(User::factory()->create(['status' => 'active'])->assignRole('finance'));
    Filament::setCurrentPanel(Filament::getPanel('finance'));
    $groups = Filament::getNavigation();
    $sales = collect($groups)->first(fn ($group): bool => $group->getLabel() === __('navigation.groups.sales'));
    expect(array_map(fn ($item): string => $item->getUrl(), array_slice($sales->getItems(), 0, 3)))->toBe([
        ManualPaymentProofResource::getUrl(),
        RefundResource::getUrl(),
        OrderResource::getUrl(),
    ]);
})->group('T-SIDEBAR-V2', 'case-2');

it('registers OrderResource in admin and finance but never in instructor', function (): void {
    expect(Filament::getPanel('admin')->getResources())->toContain(OrderResource::class)
        ->and(Filament::getPanel('finance')->getResources())->toContain(OrderResource::class)
        ->and(Filament::getPanel('instructor')->getResources())->not->toContain(OrderResource::class);
})->group('T-SIDEBAR-V2', 'case-3');

it('shows no queue badge to a user without access to the resource', function (): void {
    $marketing = User::factory()->create(['status' => 'active'])->assignRole('marketing');
    $this->actingAs($marketing);

    expect(RefundResource::getNavigationBadge())->toBeNull();
})->group('T-SIDEBAR-V2', 'case-5');

it('shows no badge and a warning colour when nothing waits, and caches the count per user', function (): void {
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $this->actingAs($admin);

    expect(RefundResource::getNavigationBadge())->toBeNull()
        ->and(RefundResource::getNavigationBadgeColor())->toBe('warning')
        ->and(Cache::has('nav-badge:'.RefundResource::class.':'.$admin->id))->toBeTrue();
})->group('T-SIDEBAR-V2', 'case-5');

it('keeps the badge cache separate for each user', function (): void {
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $finance = User::factory()->create(['status' => 'active'])->assignRole('finance');

    $this->actingAs($admin);
    RefundResource::getNavigationBadge();
    $this->actingAs($finance);
    RefundResource::getNavigationBadge();

    expect(Cache::has('nav-badge:'.RefundResource::class.':'.$admin->id))->toBeTrue()
        ->and(Cache::has('nav-badge:'.RefundResource::class.':'.$finance->id))->toBeTrue();
})->group('T-SIDEBAR-V2', 'case-5');

it('lets payment.view holders open the orders resource and blocks writes', function (): void {
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $this->actingAs($admin);

    expect(OrderResource::canViewAny())->toBeTrue()
        ->and(OrderResource::canCreate())->toBeFalse();

    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $this->actingAs($instructor);
    expect(OrderResource::canViewAny())->toBeFalse();
})->group('T-SIDEBAR-V2', 'case-6');

it('lists the order details and payment method in each staff panel and language', function (string $panel, string $locale): void {
    $staff = User::factory()->create(['status' => 'active', 'locale' => $locale])->assignRole($panel);
    $payer = User::factory()->create(['display_name' => 'Order Payer']);
    $createOrder = static fn (string $reference, OrderStatus $status): Order => Order::query()->create([
        'reference' => $reference,
        'payer_id' => $payer->id,
        'market' => Market::Egypt->value,
        'currency' => 'EGP',
        'subtotal_minor' => 12575,
        'total_minor' => 12345,
        'discount_minor' => 230,
        'status' => $status,
        'idempotency_key' => $reference,
        'request_hash' => hash('sha256', $reference),
    ]);
    $createAttempt = static fn (Order $order, int $sequence, string $gateway): PaymentAttempt => PaymentAttempt::query()->create([
        'order_id' => $order->id,
        'sequence' => $sequence,
        'gateway' => $gateway,
        'reference' => $order->reference.'-'.$sequence,
        'currency' => $order->currency,
        'amount_minor' => $order->total_minor,
        'expires_at' => now()->addHour(),
    ]);

    $fulfilled = $createOrder('GATEWAY-ORDER', OrderStatus::Paid);
    $paymobAttempt = $createAttempt($fulfilled, 1, 'paymob');
    $createAttempt($fulfilled, 2, 'fake');
    PaymentEvent::query()->create([
        'order_id' => $fulfilled->id,
        'payment_attempt_id' => $paymobAttempt->id,
        'gateway' => 'paymob',
        'event_key' => 'orders-paymob-success',
        'provider_event_id' => 'orders-paymob-success',
        'state' => 'success',
        'currency' => $fulfilled->currency,
        'amount_minor' => $fulfilled->total_minor,
        'outcome' => PaymentEventOutcome::Fulfilled,
        'processed_at' => now(),
    ]);
    $course = Course::query()->create([
        'title' => ['en' => 'Drawing course', 'ar' => 'دورة رسم'],
        'slug_en' => 'drawing-course',
        'slug_ar' => 'دورة-رسم',
        'status' => 'published',
        'access_mode' => 'paid',
    ]);
    $fulfilled->items()->create([
        'learner_id' => $payer->id,
        'product_type' => ProductType::Course,
        'product_id' => $course->id,
        'unit_price_minor' => $fulfilled->subtotal_minor,
        'currency' => $fulfilled->currency,
        'title_en' => 'Drawing course',
        'title_ar' => 'دورة رسم',
    ]);
    Entitlement::query()->create([
        'learner_id' => $payer->id,
        'course_id' => $course->id,
        'source_type' => EntitlementSource::Purchase,
        'source_ref' => 'order:'.$fulfilled->reference,
        'starts_at' => now(),
        'status' => Entitlement::ACTIVE,
    ]);

    $manual = $createOrder('TRANSFER-ORDER', OrderStatus::Pending);
    $createAttempt($manual, 1, 'fake');
    ManualPaymentProof::query()->create([
        'order_id' => $manual->id,
        'payer_id' => $payer->id,
        'payer_name' => $payer->display_name,
        'sender_account' => '01234567890',
        'transfer_reference' => 'TEST-TRANSFER',
        'currency' => $manual->currency,
        'amount_minor' => $manual->total_minor,
        'transferred_at' => now(),
        'file_path' => 'proofs/test.png',
        'file_mime' => 'image/png',
        'file_size' => 32,
        'file_sha256' => hash('sha256', 'test proof'),
        'sla_due_at' => now()->addDay(),
    ]);
    $unattempted = $createOrder('NO-PAYMENT-ORDER', OrderStatus::Pending);

    $manualFallbacks = array_map(
        static fn (OrderStatus $status): Order => $createOrder('MANUAL-'.$status->value, $status),
        [OrderStatus::Paid, OrderStatus::Refunded, OrderStatus::PartiallyRefunded, OrderStatus::Disputed],
    );
    $attemptFallbacks = array_map(function (OrderStatus $status) use ($createOrder, $createAttempt): Order {
        $order = $createOrder('ATTEMPT-'.$status->value, $status);
        $createAttempt($order, 1, 'paymob');
        $createAttempt($order, 2, 'fake');

        return $order;
    }, [OrderStatus::Pending, OrderStatus::Failed, OrderStatus::Cancelled]);

    $ordersBefore = DB::table('orders')->orderBy('id')->get()->all();
    $entitlementsBefore = DB::table('entitlements')->orderBy('id')->get()->all();

    $this->actingAs($staff)->get(route("filament.{$panel}.resources.orders.index"))
        ->assertOk()
        ->assertSee(__('orders.col_gateway', [], $locale))
        ->assertSee($fulfilled->reference);
    app()->setLocale($locale);
    Filament::setCurrentPanel(Filament::getPanel($panel));

    $table = Livewire::test(ListOrders::class)
        ->assertTableColumnStateSet('reference', $fulfilled->reference, $fulfilled)
        ->assertTableColumnStateSet('payer.display_name', 'Order Payer', $fulfilled)
        ->assertTableColumnStateSet('total_minor', (new PriceFormatter)->format($fulfilled->total(), Market::Egypt, $locale), $fulfilled)
        ->assertTableColumnFormattedStateSet('status', __('refunds.order_status_paid'), $fulfilled)
        ->assertTableColumnStateSet('last_gateway', __('orders.gateway_paymob'), $fulfilled)
        ->assertTableColumnStateSet('last_gateway', __('orders.gateway_manual'), $manual)
        // An unattempted order has no gateway: the state is null and the table shows the '—' placeholder instead of a badge.
        ->assertTableColumnStateSet('last_gateway', null, $unattempted);

    foreach ($manualFallbacks as $order) {
        $table->assertTableColumnStateSet('last_gateway', __('orders.gateway_manual'), $order);
    }
    foreach ($attemptFallbacks as $order) {
        $table->assertTableColumnStateSet('last_gateway', __('orders.gateway_fake'), $order);
    }

    $table->filterTable('status', OrderStatus::Paid->value)
        ->assertCanSeeTableRecords([$fulfilled])
        ->assertCanNotSeeTableRecords([$manual, $unattempted])
        ->mountTableAction('view', $fulfilled)
        ->assertSchemaComponentStateSet('lines', [$locale === 'ar' ? 'دورة رسم' : 'Drawing course']);

    expect(DB::table('orders')->orderBy('id')->get()->all())->toEqual($ordersBefore)
        ->and(DB::table('entitlements')->orderBy('id')->get()->all())->toEqual($entitlementsBefore);
})->with([
    'admin English' => ['admin', 'en'],
    'admin Arabic' => ['admin', 'ar'],
    'finance English' => ['finance', 'en'],
    'finance Arabic' => ['finance', 'ar'],
])->group('T-SIDEBAR-V2', 'case-6', 'T-G7-FIXES-G', 'X5', 'D1', 'YG-GAP-PAY-004');
