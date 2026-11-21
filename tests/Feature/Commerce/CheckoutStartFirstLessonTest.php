<?php

// T-P8-03 | J01: a paid single-course order for the signed-in learner offers "Start first lesson" | A1

declare(strict_types=1);

use App\Domain\Commerce\Actions\CreateOrder;
use App\Domain\Commerce\Actions\TransitionOrder;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\PaymentAttempt;
use App\Domain\Commerce\Payments\FakePaymentGateway;
use App\Domain\Commerce\ProductType;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\Shared\Markets\Market;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

/** A published, individually sold course with an EG price. */
function scf_course(string $tag = 'A'): Course
{
    $course = Course::query()->create([
        'title' => ['en' => 'Start Course '.$tag, 'ar' => 'دورة البداية '.$tag],
        'slug_ar' => 'scf-ar-'.$tag.'-'.uniqid(), 'slug_en' => 'scf-en-'.$tag.'-'.uniqid(),
        'status' => 'published', 'access_mode' => Course::ACCESS_PAID,
    ]);
    CoursePrice::query()->create(['course_id' => $course->id, 'market' => 'EG', 'currency' => 'EGP', 'amount_minor' => 25000]);

    return $course;
}

function scf_user(string $role): User
{
    return User::factory()->create(['status' => 'active'])->assignRole($role);
}

/** Starts checkout over HTTP and returns the order the redirect points to. */
function scf_start(User $payer, Course $course, User $learner, string $key): Order
{
    test()->actingAs($payer)->post(route('checkout.start'), [
        'type' => 'course', 'product_id' => $course->id, 'learner_id' => $learner->id, 'key' => $key,
    ])->assertRedirect();

    return Order::query()->where('payer_id', $payer->id)->latest('id')->firstOrFail();
}

/** Pays the order by card and sends the signed webhook, so the order becomes paid through the real path. */
function scf_pay(User $payer, Order $order): Order
{
    test()->actingAs($payer)->post(route('checkout.pay', $order), ['method' => 'card', 'terms' => '1'])->assertRedirect();
    $attempt = PaymentAttempt::query()->where('order_id', $order->id)->latest('sequence')->firstOrFail();
    $payload = ['id' => 'scf-'.uniqid(), 'reference' => $attempt->reference, 'amount_minor' => $order->total_minor, 'currency' => 'EGP', 'success' => true];
    test()->postJson(route('webhooks.paymob'), $payload, ['X-Payment-Signature' => FakePaymentGateway::sign($payload)])->assertOk();

    return $order->fresh();
}

it('a paid single-course order for the signed-in learner shows the start link to the course and the courses link', function (): void {
    $student = scf_user('student');
    $course = scf_course('A');

    $order = scf_pay($student, scf_start($student, $course, $student, 'scf-self-0001'));
    expect($order->status)->toBe(OrderStatus::Paid);

    $this->actingAs($student)->get(route('checkout.order', $order))
        ->assertOk()
        ->assertSee('data-start-first-lesson', false)
        ->assertSee('href="'.route('learn.course', $course->id).'"', false)
        ->assertSee(__('checkout.start_first_lesson'))
        ->assertSee(__('checkout.go_to_courses'));
})->group('T-P8-03', 'A1');

it('a paid order bought by a parent for a child shows no start link, only the courses link', function (): void {
    $parent = scf_user('parent');
    $child = scf_user('student');
    GuardianChildLink::query()->create([
        'guardian_id' => $parent->id, 'child_id' => $child->id, 'status' => GuardianChildLink::STATUS_VERIFIED,
        'method' => GuardianChildLink::METHOD_CREATED_BY_PARENT, 'verified_at' => now(),
    ]);
    $course = scf_course('B');

    $order = scf_pay($parent, scf_start($parent, $course, $child, 'scf-child-0001'));
    expect($order->status)->toBe(OrderStatus::Paid);

    $this->actingAs($parent)->get(route('checkout.order', $order))
        ->assertOk()
        ->assertDontSee('data-start-first-lesson', false)
        ->assertDontSee(route('learn.course', $course->id), false)
        ->assertSee(__('checkout.go_to_courses'));
})->group('T-P8-03', 'A1');

it('a paid order with two courses shows no start link, only the courses link', function (): void {
    $student = scf_user('student');
    $first = scf_course('C1');
    $second = scf_course('C2');

    $order = app(CreateOrder::class)($student, Market::Egypt, [
        new OrderLine(ProductType::Course, $first->id, $student),
        new OrderLine(ProductType::Course, $second->id, $student),
    ], 'scf-two-0001');
    $order = app(TransitionOrder::class)($order, OrderStatus::Paid, null);
    expect($order->status)->toBe(OrderStatus::Paid);

    $this->actingAs($student)->get(route('checkout.order', $order))
        ->assertOk()
        ->assertDontSee('data-start-first-lesson', false)
        ->assertDontSee(route('learn.course', $first->id), false)
        ->assertDontSee(route('learn.course', $second->id), false)
        ->assertSee(__('checkout.go_to_courses'));
})->group('T-P8-03', 'A1');
