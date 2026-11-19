<?php

declare(strict_types=1);

// T-G7-FIXES-E | P2 (proof request attributes), P1 (d/m/Y date input) | E1 E2

use App\Domain\Commerce\Actions\CreateOrder;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Commerce\ManualPaymentProof;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\ProductType;
use App\Domain\Shared\Markets\Market;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('local');
    Storage::fake('public');
    Carbon::setTestNow('2026-11-19 09:00:00');
});

afterEach(fn () => Carbon::setTestNow());

// ---------------------------------------------------------------------------
// Helpers (prefix g7e)
// ---------------------------------------------------------------------------

function g7eUser(string $locale = 'en'): User
{
    return User::factory()->create(['status' => 'active', 'locale' => $locale])->assignRole('student');
}

/** A pending Egyptian order of one priced course (250.00 EGP) for the payer. */
function g7eOrder(User $payer): Order
{
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة إثبات', 'en' => 'Proof course '.uniqid()],
        'slug_ar' => 'g7e-ar-'.uniqid(), 'slug_en' => 'g7e-en-'.uniqid(),
        'status' => 'published', 'access_mode' => 'paid',
    ]);
    CoursePrice::query()->create(['course_id' => $course->id, 'market' => Market::Egypt->value, 'currency' => 'EGP', 'amount_minor' => 25000]);

    return app(CreateOrder::class)($payer, Market::Egypt, [new OrderLine(ProductType::Course, $course->id, $payer)], 'g7e-'.uniqid());
}

/** A complete, valid form body; overrides replace single fields. */
function g7eForm(Order $order, array $overrides = []): array
{
    return array_merge([
        'payer_name' => 'Hoda Payer',
        'sender_account' => '01099887766',
        'transfer_reference' => 'TRX-'.$order->getKey(),
        'amount' => '250.00',
        'transferred_at' => '18/11/2026 14:30',
        'proof' => UploadedFile::fake()->image('slip.png', 160, 120),
    ], $overrides);
}

function g7eHasAsciiLetters(string $message): bool
{
    return preg_match('/[A-Za-z]/', $message) === 1;
}

// ---------------------------------------------------------------------------
// E1: the proof request reports the lang attribute names
// ---------------------------------------------------------------------------

it('E1 an empty proof form reports every field with its English attribute name and no raw field name', function (): void {
    $payer = g7eUser('en');
    $order = g7eOrder($payer);

    $response = $this->actingAs($payer)->from(route('manual-payments.create', $order))
        ->post(route('manual-payments.store', $order), []);

    $response->assertSessionHasErrors([
        'payer_name' => 'The sender name field is required.',
        'transferred_at' => 'The transfer date and time field is required.',
        'proof' => 'The transfer proof file field is required.',
    ]);

    $messages = session('errors')->all();
    foreach ($messages as $message) {
        expect($message)->not->toContain('payer_name')
            ->and($message)->not->toContain('transferred_at')
            ->and($message)->not->toContain('proof field');
    }
})->group('T-G7-FIXES-E', 'E1');

it('E1 the Arabic proof messages use Arabic attribute names and contain no ASCII letters', function (): void {
    $payer = g7eUser('ar');
    $order = g7eOrder($payer);

    $this->actingAs($payer)->from(route('manual-payments.create', $order))
        ->post(route('manual-payments.store', $order), [])
        ->assertSessionHasErrors(['payer_name', 'sender_account', 'transfer_reference', 'amount', 'transferred_at', 'proof']);

    $messages = session('errors')->all();
    expect($messages)->not->toBeEmpty();
    foreach ($messages as $message) {
        expect(g7eHasAsciiLetters($message))->toBeFalse("Message leaks Latin text: {$message}");
    }
    expect(implode(' | ', $messages))->toContain('اسم المرسل')->toContain('مرجع التحويل');
})->group('T-G7-FIXES-E', 'E1');

it('E1 every manual_payment attribute key, validation.regex and validation.file exist in both locales', function (): void {
    foreach (['payer_name', 'sender_account', 'reference', 'amount', 'transferred_at', 'proof'] as $key) {
        expect(Lang::has('manual_payment.attr_'.$key, 'en'))->toBeTrue()
            ->and(Lang::has('manual_payment.attr_'.$key, 'ar'))->toBeTrue();
    }
    foreach (['en', 'ar'] as $locale) {
        expect(Lang::has('validation.regex', $locale))->toBeTrue()
            ->and(Lang::has('validation.file', $locale))->toBeTrue();
    }
})->group('T-G7-FIXES-E', 'E1');

it('E1 a payer who is not the order payer gets 404 even with invalid input, because authorize runs first', function (): void {
    $payer = g7eUser();
    $order = g7eOrder($payer);
    $stranger = g7eUser();

    $this->actingAs($stranger)->post(route('manual-payments.store', $order), [])->assertNotFound();
    expect(ManualPaymentProof::query()->count())->toBe(0);
})->group('T-G7-FIXES-E', 'E1');

// ---------------------------------------------------------------------------
// E2: the transfer date field is a dd/mm/yyyy hh:mm text input
// ---------------------------------------------------------------------------

it('E2 the transfer date renders as a text input with the placeholder and a linked hint, never datetime-local', function (): void {
    $payer = g7eUser('en');
    $order = g7eOrder($payer);

    $html = $this->actingAs($payer)->get(route('manual-payments.create', $order))->assertOk()->getContent();

    expect($html)->not->toContain('datetime-local')
        ->and($html)->toContain('id="transferred_at"')
        ->and($html)->toContain('type="text"')
        ->and($html)->toContain('placeholder="dd/mm/yyyy hh:mm"')
        ->and($html)->toContain('aria-describedby="transferred_at-help"')
        ->and($html)->toContain('id="transferred_at-help"')
        ->and($html)->toContain(__('manual_payment.transferred_at_hint', [], 'en'));
})->group('T-G7-FIXES-E', 'E2');

it('E2 the server accepts the d/m/Y forms and ISO forms and stores the typed wall time in the app timezone', function (string $typed, string $expected): void {
    $payer = g7eUser('en');
    $order = g7eOrder($payer);

    $this->actingAs($payer)->post(route('manual-payments.store', $order), g7eForm($order, ['transferred_at' => $typed]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('manual-payments.create', $order));

    $proof = ManualPaymentProof::query()->where('order_id', $order->getKey())->latest('id')->firstOrFail();
    expect($proof->transferred_at->format('Y-m-d H:i:s'))->toBe($expected);
})->with([
    ['18/11/2026 14:30', '2026-11-18 14:30:00'],
    ['18/11/2026 14:30:05', '2026-11-18 14:30:05'],
    ['18/11/2026', '2026-11-18 12:00:00'],
    ['2026-11-18 14:30:00', '2026-11-18 14:30:00'],
    ['2026-11-18T14:30', '2026-11-18 14:30:00'],
])->group('T-G7-FIXES-E', 'E2');

it('E2 rejects an impossible date, an impossible time and free text with the format message under transferred_at', function (string $typed): void {
    $payer = g7eUser('en');
    $order = g7eOrder($payer);

    $this->actingAs($payer)->from(route('manual-payments.create', $order))
        ->post(route('manual-payments.store', $order), g7eForm($order, ['transferred_at' => $typed]))
        ->assertSessionHasErrors(['transferred_at' => __('manual_payment.date_format_invalid', [], 'en')]);
    expect(ManualPaymentProof::query()->count())->toBe(0);
})->with(['18/11/2026 25:99', 'yesterday afternoon'])->group('T-G7-FIXES-E', 'E2');

it('E2 an impossible calendar day (31/02/2026) is refused as a format error', function (): void {
    $payer = g7eUser('en');
    $order = g7eOrder($payer);

    $this->actingAs($payer)->from(route('manual-payments.create', $order))
        ->post(route('manual-payments.store', $order), g7eForm($order, ['transferred_at' => '31/02/2026 10:00']))
        ->assertSessionHasErrors(['transferred_at' => __('manual_payment.date_format_invalid', [], 'en')]);
    expect(ManualPaymentProof::query()->count())->toBe(0);
});

it('E2 a parsed future time or one older than the allowed age is refused by the Action with transfer_date_invalid', function (string $typed): void {
    $payer = g7eUser('en');
    $order = g7eOrder($payer);

    $this->actingAs($payer)->from(route('manual-payments.create', $order))
        ->post(route('manual-payments.store', $order), g7eForm($order, ['transferred_at' => $typed]))
        ->assertSessionHasErrors();

    expect(session('errors')->all())->toContain(__('manual_payment.transfer_date_invalid', [], 'en'));
    expect(ManualPaymentProof::query()->count())->toBe(0);
})->with(['19/11/2026 15:00', '01/10/2026 10:00'])->group('T-G7-FIXES-E', 'E2');
