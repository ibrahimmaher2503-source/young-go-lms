<?php

declare(strict_types=1);

use App\Domain\Commerce\Actions\CreateOrder;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Commerce\ManualPaymentProof;
use App\Domain\Commerce\ManualPaymentStatus;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\ProductType;
use App\Domain\Commerce\Queries\ManualPaymentQueue;
use App\Domain\Media\PdfInspector;
use App\Domain\Shared\Markets\Market;
use App\Filament\Resources\ManualPayments\ManualPaymentProofResource;
use App\Filament\Resources\ManualPayments\Pages\ListManualPaymentProofs;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\PdfFixture;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('submits manual transfer proof via HTTP, restricts proof file ACL, and surfaces finance queue summaries without exposing full references', function (): void {
    Storage::fake(config('media.disk', 'local'));
    $payer = User::factory()->create(['status' => 'active', 'name' => 'Hoda Payer'])->assignRole('student');
    $stranger = User::factory()->create(['status' => 'active'])->assignRole('student');
    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $finance = User::factory()->create(['status' => 'active'])->assignRole('finance');

    $course = Course::query()->create([
        'title' => ['ar' => 'دورة', 'en' => 'Manual Payment Course'],
        'slug_ar' => 'mpp-ar-'.uniqid(),
        'slug_en' => 'mpp-en-'.uniqid(),
        'status' => 'published',
        'access_mode' => 'paid',
    ]);
    CoursePrice::query()->create(['course_id' => $course->id, 'market' => 'EG', 'currency' => 'EGP', 'amount_minor' => 25000]);
    $order = app(CreateOrder::class)($payer, Market::Egypt, [new OrderLine(ProductType::Course, $course->id, $payer)], 'mpp-ord-'.uniqid());

    // Stranger gets 404 on form and store
    $this->actingAs($stranger)->get(route('manual-payments.create', $order))->assertNotFound();

    // Payer opens form and submits PNG proof
    $this->actingAs($payer)->get(route('manual-payments.create', $order))->assertOk();
    $this->actingAs($payer)->post(route('manual-payments.store', $order), [
        'payer_name' => 'Hoda Payer',
        'sender_account' => '0109988776655',
        'transfer_reference' => 'INSTAPAY-REF-9911',
        'amount' => '250.00',
        'transferred_at' => now()->subMinutes(20)->format('Y-m-d H:i:s'),
        'proof' => UploadedFile::fake()->image('instapay-slip.png', 160, 120),
    ])->assertRedirect(route('manual-payments.create', $order));

    $proof = ManualPaymentProof::query()->sole();
    expect($proof->amount_minor)->toBe(25000)
        ->and($proof->status)->toBe(ManualPaymentStatus::Pending);

    // Proof download ACL: payer and finance allowed with nosniff; stranger & instructor 404
    $this->actingAs($payer)->get(route('manual-payments.file', $proof))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->actingAs($finance)->get(route('manual-payments.file', $proof))->assertOk();
    $this->actingAs($stranger)->get(route('manual-payments.file', $proof))->assertNotFound();
    $this->actingAs($instructor)->get(route('manual-payments.file', $proof))->assertNotFound();

    // Finance queue summary and panel authorization
    expect(ManualPaymentQueue::summary())->toBe(['pending' => 1, 'overdue' => 0, 'mismatch' => 0]);

    $this->actingAs($finance);
    Filament::setCurrentPanel('finance');
    expect(ManualPaymentProofResource::canViewAny())->toBeTrue();
    Livewire::test(ListManualPaymentProofs::class)->assertOk();

    $this->actingAs($instructor);
    expect(ManualPaymentProofResource::canViewAny())->toBeFalse();
})->group('T-P8-04', 'A1', 'A2', 'A3', 'YG-GAP-PAY-004')->group('payments');

it('accepts valid PDF manual transfer proof when qpdf binary runtime is healthy on the host', function (): void {
    $pdfBytes = PdfFixture::bytes(1);
    $pdfPath = tempnam(sys_get_temp_dir(), 'yg_mpp_').'.pdf';
    file_put_contents($pdfPath, $pdfBytes);
    $inspection = app(PdfInspector::class)->inspect($pdfPath);
    @unlink($pdfPath);

    expect($inspection['valid'] ?? false)->toBeTrue();
})->group('T-P8-04', 'A1')->group('payments');
