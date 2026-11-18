<?php

declare(strict_types=1);

// T-P8-GAPS | PRD 17, 29.7 | A1 A2 A3 A4 A5

use App\Domain\Commerce\Actions\CreateOrder;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\PaymentAttempt;
use App\Domain\Commerce\PaymentEvent;
use App\Domain\Commerce\Payments\CheckoutIntent;
use App\Domain\Commerce\Payments\FakePaymentGateway;
use App\Domain\Commerce\ProductType;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Shared\Markets\Market;
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
// Helpers (prefix tp8gf)
// ---------------------------------------------------------------------------

function tp8gfLearner(): User
{
    return User::factory()->create(['status' => 'active', 'locale' => 'en'])->assignRole('student');
}

/** A sold course with an Egypt price of 250 EGP. */
function tp8gfCourse(): Course
{
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة البوابة', 'en' => 'Fake Gateway Course '.uniqid()],
        'slug_ar' => 'tp8gf-ar-'.uniqid(), 'slug_en' => 'tp8gf-en-'.uniqid(),
        'status' => 'published', 'access_mode' => 'paid',
    ]);
    CoursePrice::query()->create(['course_id' => $course->id, 'market' => Market::Egypt->value, 'currency' => 'EGP', 'amount_minor' => 25000]);

    return $course;
}

function tp8gfOrder(User $learner, Course $course): Order
{
    return app(CreateOrder::class)($learner, Market::Egypt, [new OrderLine(ProductType::Course, $course->id, $learner)], 'tp8gf-'.uniqid());
}

/** Starts a card payment through the checkout, which opens a Fake gateway attempt for the order. */
function tp8gfOpenAttempt(User $learner, Order $order): PaymentAttempt
{
    test()->actingAs($learner)->post(route('checkout.pay', $order), ['method' => 'card', 'terms' => '1'])->assertRedirect();

    return PaymentAttempt::query()->where('order_id', $order->id)->latest('sequence')->firstOrFail();
}

// ---------------------------------------------------------------------------
// The page: Pay and Fail buttons for an open fake attempt
// ---------------------------------------------------------------------------

it('A1 the fake gateway page shows the order reference and the Pay and Fail buttons for an open attempt', function (): void {
    $learner = tp8gfLearner();
    $order = tp8gfOrder($learner, tp8gfCourse());
    $attempt = tp8gfOpenAttempt($learner, $order);

    $this->get('/fake-gateway/'.$attempt->reference)
        ->assertOk()
        ->assertSee('data-fake-gateway', false)
        ->assertSee('data-fake-pay', false)
        ->assertSee('data-fake-fail', false)
        ->assertSee($order->reference);
})->group('T-P8-GAPS', 'A1');

it('A1 an unknown, non-fake or already closed attempt is 404 on the page and on both posts', function (): void {
    $this->get('/fake-gateway/does-not-exist')->assertNotFound();
    $this->post('/fake-gateway/does-not-exist', ['outcome' => 'pay'])->assertNotFound();

    $learner = tp8gfLearner();
    $order = tp8gfOrder($learner, tp8gfCourse());
    $attempt = tp8gfOpenAttempt($learner, $order);
    $attempt->forceFill(['gateway' => 'paymob'])->save();
    $this->get('/fake-gateway/'.$attempt->reference)->assertNotFound();

    $learner2 = tp8gfLearner();
    $order2 = tp8gfOrder($learner2, tp8gfCourse());
    $closed = tp8gfOpenAttempt($learner2, $order2);
    $this->post('/fake-gateway/'.$closed->reference, ['outcome' => 'pay'])->assertRedirect();
    $this->get('/fake-gateway/'.$closed->reference)->assertNotFound();
    $this->post('/fake-gateway/'.$closed->reference, ['outcome' => 'fail'])->assertNotFound();
})->group('T-P8-GAPS', 'A1');

it('A1 the fake gateway page and both posts are 404 in the production environment', function (): void {
    $learner = tp8gfLearner();
    $order = tp8gfOrder($learner, tp8gfCourse());
    $attempt = tp8gfOpenAttempt($learner, $order);

    app()->detectEnvironment(fn () => 'production');

    $this->get('/fake-gateway/'.$attempt->reference)->assertNotFound();
    // Post a matching CSRF token so the request reaches the controller and the 404 comes from the environment gate.
    $this->withSession(['_token' => 'tp8gf-token'])
        ->post('/fake-gateway/'.$attempt->reference, ['_token' => 'tp8gf-token', 'outcome' => 'pay'])->assertNotFound();

    expect($order->refresh()->status)->toBe(OrderStatus::Pending);
})->group('T-P8-GAPS', 'A1');

// ---------------------------------------------------------------------------
// Pay, fail, repeat and validation
// ---------------------------------------------------------------------------

it('A1 posting pay marks the order paid, grants access and redirects to the checkout return page', function (): void {
    $learner = tp8gfLearner();
    $course = tp8gfCourse();
    $order = tp8gfOrder($learner, $course);
    $attempt = tp8gfOpenAttempt($learner, $order);

    $this->post('/fake-gateway/'.$attempt->reference, ['outcome' => 'pay'])
        ->assertRedirect(route('checkout.return', ['order' => $order->reference]));

    expect($order->refresh()->status)->toBe(OrderStatus::Paid);
    app(EntitlementService::class)->flush();
    expect(app(EntitlementService::class)->currentFor($learner, $course))->not->toBeNull();
})->group('T-P8-GAPS', 'A1');

it('A1 posting fail closes the attempt as failed, leaves the order pending and a new pay is possible', function (): void {
    $learner = tp8gfLearner();
    $order = tp8gfOrder($learner, tp8gfCourse());
    $attempt = tp8gfOpenAttempt($learner, $order);

    $this->post('/fake-gateway/'.$attempt->reference, ['outcome' => 'fail'])
        ->assertRedirect(route('checkout.return', ['order' => $order->reference]));

    expect($attempt->refresh()->status->isOpen())->toBeFalse()
        ->and($order->refresh()->status)->toBe(OrderStatus::Pending);

    $retry = tp8gfOpenAttempt($learner, $order);
    expect($retry->id)->not->toBe($attempt->id)
        ->and($retry->status->isOpen())->toBeTrue();
    $this->get('/fake-gateway/'.$retry->reference)->assertOk()->assertSee('data-fake-pay', false);
})->group('T-P8-GAPS', 'A1');

it('A1 repeating the pay post is idempotent: one payment event, the order stays paid', function (): void {
    $learner = tp8gfLearner();
    $order = tp8gfOrder($learner, tp8gfCourse());
    $attempt = tp8gfOpenAttempt($learner, $order);

    $this->post('/fake-gateway/'.$attempt->reference, ['outcome' => 'pay'])->assertRedirect();
    $events = PaymentEvent::query()->where('order_id', $order->id)->count();

    // The attempt is closed now, so the repeat is 404; the signed event key is the same either way.
    $this->post('/fake-gateway/'.$attempt->reference, ['outcome' => 'pay'])->assertNotFound();

    expect(PaymentEvent::query()->where('order_id', $order->id)->count())->toBe($events)
        ->and($order->refresh()->status)->toBe(OrderStatus::Paid);
})->group('T-P8-GAPS', 'A1');

it('A1 an invalid outcome is a validation error and changes nothing', function (): void {
    $learner = tp8gfLearner();
    $order = tp8gfOrder($learner, tp8gfCourse());
    $attempt = tp8gfOpenAttempt($learner, $order);

    $this->post('/fake-gateway/'.$attempt->reference, ['outcome' => 'maybe'])->assertSessionHasErrors('outcome');
    $this->post('/fake-gateway/'.$attempt->reference, [])->assertSessionHasErrors('outcome');

    expect($order->refresh()->status)->toBe(OrderStatus::Pending)
        ->and($attempt->refresh()->status->isOpen())->toBeTrue();
})->group('T-P8-GAPS', 'A1');

// ---------------------------------------------------------------------------
// The gateway's checkout URL by environment
// ---------------------------------------------------------------------------

it('A1 in testing the fake gateway checkout URL stays on the fake host', function (): void {
    $learner = tp8gfLearner();
    $order = tp8gfOrder($learner, tp8gfCourse());

    $response = $this->actingAs($learner)->post(route('checkout.pay', $order), ['method' => 'card', 'terms' => '1']);

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toStartWith('https://fake-gateway.test/');
})->group('T-P8-GAPS', 'A1');

it('A1 in the local environment the fake gateway checkout URL is the app page for the attempt', function (): void {
    // The gateway is built once per app: switch the environment, then ask the gateway for a checkout directly.
    app()->detectEnvironment(fn () => 'local');

    $session = (new FakePaymentGateway)->createCheckout(new CheckoutIntent(
        'tp8gf-local-ref', 'TP8GF-ORDER', 25000, 'EGP', [], ['card'], 'Learner', 'learner@example.test',
        'https://example.test/notify', 'https://example.test/return',
    ));

    expect($session->redirectUrl)->toBe(route('fake-gateway.show', ['reference' => 'tp8gf-local-ref']));
})->group('T-P8-GAPS', 'A1');
