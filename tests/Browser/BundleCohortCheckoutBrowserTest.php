<?php

declare(strict_types=1);

use App\Domain\Commerce\Bundle;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Entitlements\EntitlementService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('shows the linked learner cohort and continues to its bundle order', function (string $locale, int $width, int $height): void {
    Http::preventStrayRequests();
    Storage::fake(config('media.disk'));
    Storage::fake('public');
    $this->artisan('demo:seed')->assertSuccessful();
    $parent = User::query()->where('email', 'parent@demo.younggo.test')->sole();
    $parent->forceFill(['locale' => $locale])->save();
    $child = User::query()->where('username', 'demo.adam')->sole();
    $bundle = Bundle::query()->with('items.course')->sole();
    $course = $bundle->items->sole(fn ($item): bool => $item->course->cohorts()->exists())->course;
    $cohort = $course->cohorts()->sole();
    $selector = "#cohort-{$child->id}-{$course->id}";
    $this->actingAs($parent)->withSession(['locale' => $locale]);

    $page = $this->visit("/checkout/bundle/{$bundle->id}")->resize($width, $height);
    $page->select('#learner_id', (string) $child->id)
        ->assertVisible($selector)
        ->select($selector, (string) $cohort->id)
        ->assertNoAccessibilityIssues()->assertNoJavaScriptErrors();
    expect($page->script("() => document.querySelector('{$selector}').selectedOptions[0].textContent"))->toContain($cohort->localizedName($locale))
        ->and($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
    $directory = storage_path('app/review-media/bundle-checkout');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    if ($width === 390) {
        $page->script("() => document.querySelector('{$selector}').scrollIntoView({ block: 'center' })");
    }
    if ($locale === 'ar' && $width === 1440) {
        $page->resize(1568, 1003)->screenshot(fullPage: false, filename: '../../../storage/app/review-media/bundle-checkout/ar-1568');
        $page->resize($width, $height);
    }
    $page->screenshot(fullPage: false, filename: "../../../storage/app/review-media/bundle-checkout/{$locale}-{$width}")
        ->click('[data-checkout-learner] button[type=submit]')
        ->assertVisible('[data-payer-details]');

    $order = Order::query()->where('payer_id', $parent->id)->whereNotIn('idempotency_key', ['demo-order-003'])->sole();
    $page->assertPathIs("/checkout/orders/{$order->id}");
    expect($order->items()->sole()->learner_id)->toBe($child->id);
    if ($locale === 'ar' && $width === 390) {
        config(['payments.driver' => 'fake', 'payments.live_enabled' => false]);
        $this->app['env'] = 'local';
        $page->check('#method-card')->check('#terms')->click('button[form="checkout-pay-form"]')
            ->assertVisible('[data-fake-gateway]');
        expect($order->refresh()->status)->toBe(OrderStatus::Pending)
            ->and(app(EntitlementService::class)->canAccessCourse($child, $course)->allowed)->toBeFalse();
        $page->click('[data-fake-pay]')->assertVisible('[data-status="paid"]');
        expect($order->refresh()->status)->toBe(OrderStatus::Paid)->and($cohort->hasSeat($child))->toBeTrue();
    }
})->with(['ar', 'en'])->with([[390, 844], [1440, 900]])
    ->group('browser')->group('T-P8-12', 'A1', 'A3', 'YG-GAP-COH-002');
