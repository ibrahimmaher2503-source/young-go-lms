<?php

declare(strict_types=1);

use App\Domain\Commerce\Actions\ApproveRefund;
use App\Domain\Commerce\Actions\CreateOrder;
use App\Domain\Commerce\Actions\ExecuteRefund;
use App\Domain\Commerce\Actions\GrantOrderEntitlements;
use App\Domain\Commerce\Actions\IssueReceipt;
use App\Domain\Commerce\Actions\RequestRefund;
use App\Domain\Commerce\Actions\TransitionOrder;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\ProductType;
use App\Domain\Commerce\ReceiptPdf;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Shared\Markets\Market;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// The receipt is a standalone PDF document rendered by headless Chromium (ReceiptPdf / spatie-laravel-pdf). A PDF download cannot be
// screenshot by the browser, so, like CertificateVerificationBrowserTest, the exact standalone HTML handed to the renderer is served
// on a test-only route. The real PDF endpoint needs Browsershot/Chromium and is covered by the Feature tests (Pdf::fake).

it('renders the paid order receipt for the payer in Arabic and English with the reference and amount visible', function (string $locale, int $width, int $height): void {
    $this->seed(RolePermissionSeeder::class);
    $payer = User::factory()->create(['status' => 'active', 'locale' => $locale])->assignRole('student');
    $staff = User::factory()->create(['status' => 'active'])->assignRole('finance');
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة الإيصال', 'en' => 'Receipt course '.uniqid()],
        'slug_ar' => 'receipt-ar-'.uniqid(), 'slug_en' => 'receipt-en-'.uniqid(),
        'status' => 'published', 'access_mode' => 'paid',
    ]);
    CoursePrice::query()->create(['course_id' => $course->id, 'market' => 'EG', 'currency' => 'EGP', 'amount_minor' => 25000]);
    $order = app(CreateOrder::class)($payer, Market::Egypt, [new OrderLine(ProductType::Course, $course->id, $payer)], 'receipt-'.uniqid());
    app(TransitionOrder::class)($order, OrderStatus::Paid);
    app(GrantOrderEntitlements::class)($order->refresh(), EntitlementSource::ManualPayment, $staff);
    $receipt = app(IssueReceipt::class)($order->refresh());

    $data = app(ReceiptPdf::class)->data($receipt, $locale);
    $path = '/_receipt-pdf-test/'.Str::lower(Str::random(16));
    $html = view('receipts.pdf', $data)->render();
    Route::middleware('web')->get($path, static fn () => response($html)->header('Content-Type', 'text/html; charset=UTF-8'));

    $page = $this->actingAs($payer)->withSession(['locale' => $locale])->visit($path)->resize($width, $height);
    $page->script('async () => { await document.fonts.ready; }');
    $page->assertVisible('.page')
        ->assertSee(__('receipts.pdf.heading', [], $locale))
        ->assertSee($data['orderReference'])
        ->assertSee($data['total'])
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();

    expect($page->script('() => [document.documentElement.lang, document.documentElement.dir]'))
        ->toBe([$locale, $locale === 'ar' ? 'rtl' : 'ltr']);
    if ($locale === 'ar') {
        expect($page->script('() => /\\p{Script=Arabic}/u.test(document.body.innerText)'))->toBeTrue();
    }
    expect($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();

    $directory = base_path('tests/Browser/screenshots/T-P8-06');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    // Pest writes below Screenshots; retain the repository's lowercase artifact directory.
    $page->screenshot(fullPage: true, filename: "../screenshots/T-P8-06/receipt-{$locale}-{$width}");
})->with(['ar', 'en'])->with([[390, 844], [1440, 900]])
    ->group('T-P8-06', 'A2', 'payments', 'browser');

it('renders the refund credit note for the payer in Arabic and English with the original receipt and refund amount visible', function (string $locale, int $width, int $height): void {
    $this->seed(RolePermissionSeeder::class);
    $payer = User::factory()->create(['status' => 'active', 'locale' => $locale])->assignRole('student');
    $finance = User::factory()->create(['status' => 'active'])->assignRole('finance');
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة الاسترداد', 'en' => 'Refund receipt course '.uniqid()],
        'slug_ar' => 'refund-receipt-ar-'.uniqid(), 'slug_en' => 'refund-receipt-en-'.uniqid(),
        'status' => 'published', 'access_mode' => 'paid',
    ]);
    CoursePrice::query()->create(['course_id' => $course->id, 'market' => 'EG', 'currency' => 'EGP', 'amount_minor' => 25000]);
    $order = app(CreateOrder::class)($payer, Market::Egypt, [new OrderLine(ProductType::Course, $course->id, $payer)], 'refund-receipt-'.uniqid());
    app(TransitionOrder::class)($order, OrderStatus::Paid);
    app(GrantOrderEntitlements::class)($order->refresh(), EntitlementSource::ManualPayment, $finance);
    $order->refresh();
    app(IssueReceipt::class)($order);

    $refund = app(RequestRefund::class)($finance, $order, 5000, 'Customer asked for a partial refund');
    app(ApproveRefund::class)($finance, $refund);
    app(ExecuteRefund::class)($finance, $refund->refresh(), 'BANK-TRX-'.uniqid());
    $credit = app(IssueReceipt::class)($order->refresh(), $refund->refresh());

    $data = app(ReceiptPdf::class)->data($credit, $locale);
    $path = '/_receipt-pdf-test/'.Str::lower(Str::random(16));
    $html = view('receipts.pdf', $data)->render();
    Route::middleware('web')->get($path, static fn () => response($html)->header('Content-Type', 'text/html; charset=UTF-8'));

    $page = $this->actingAs($payer)->withSession(['locale' => $locale])->visit($path)->resize($width, $height);
    $page->script('async () => { await document.fonts.ready; }');
    $page->assertVisible('.page')
        ->assertSee(__('receipts.pdf.refund_heading', [], $locale))
        ->assertSee($data['orderReference'])
        ->assertSee($data['total'])
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
    if ($data['originalNumber'] !== null) {
        $page->assertSee($data['originalNumber']);
    }

    expect($page->script('() => [document.documentElement.lang, document.documentElement.dir]'))
        ->toBe([$locale, $locale === 'ar' ? 'rtl' : 'ltr']);
    if ($locale === 'ar') {
        expect($page->script('() => /\\p{Script=Arabic}/u.test(document.body.innerText)'))->toBeTrue();
    }
    expect($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();

    $directory = base_path('tests/Browser/screenshots/T-P8-06');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $page->screenshot(fullPage: true, filename: "../screenshots/T-P8-06/refund-receipt-{$locale}-{$width}");
})->with(['ar', 'en'])->with([[390, 844], [1440, 900]])
    ->group('T-P8-06', 'A2', 'payments', 'browser');
