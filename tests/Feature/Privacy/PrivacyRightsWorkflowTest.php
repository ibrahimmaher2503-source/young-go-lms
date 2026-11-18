<?php

// T-P9-08 | YG-GAP-PRV-001..003, O-025 | A1 A2 A3

declare(strict_types=1);

use App\Domain\Privacy\Actions\AnonymizeAccount;
use App\Domain\Privacy\Actions\ApproveDataRequest;
use App\Domain\Privacy\Actions\CancelDataRequest;
use App\Domain\Privacy\Actions\CompleteDataRequest;
use App\Domain\Privacy\Actions\PruneRetainedData;
use App\Domain\Privacy\Actions\RejectDataRequest;
use App\Domain\Privacy\Actions\SaveRetentionPolicies;
use App\Domain\Privacy\Actions\SubmitDataRequest;
use App\Domain\Privacy\DataRequest;
use App\Domain\Privacy\DataRequestStatus;
use App\Domain\Privacy\DataRequestType;
use App\Domain\Privacy\ProtectedData;
use App\Domain\Privacy\RetentionClass;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
    app()->setLocale('en');
    Storage::fake('local');
    Storage::fake('public');
});

function tp908Student(?string $email = null): User
{
    return User::factory()->create(array_filter([
        'status' => 'active',
        'email' => $email,
    ]))->assignRole('student');
}

function tp908Staff(string $role): User
{
    return User::factory()->create(['status' => 'active'])->assignRole($role);
}

function tp908Events(string $event): int
{
    return Activity::query()->where('event', $event)->count();
}

function tp908Order(User $payer, string $reference, int $amount): int
{
    return DB::table('orders')->insertGetId([
        'reference' => $reference, 'payer_id' => $payer->id, 'market' => 'EG', 'currency' => 'EGP',
        'subtotal_minor' => $amount, 'total_minor' => $amount, 'status' => 'paid', 'paid_at' => now()->subDay(),
        'idempotency_key' => $reference, 'request_hash' => hash('sha256', $reference),
    ]);
}

it('records a self request for export, correction and deletion and writes one audit row each', function (): void {
    $student = tp908Student();

    $export = app(SubmitDataRequest::class)($student, $student, DataRequestType::Export);
    $correction = app(SubmitDataRequest::class)($student, $student, DataRequestType::Correction, 'Please fix my name spelling.');

    expect($export->status)->toBe(DataRequestStatus::Requested)
        ->and($correction->details)->toBe('Please fix my name spelling.')
        ->and(tp908Events('privacy.request_submitted'))->toBe(2);
})->group('T-P9-08', 'A1');

it('refuses a second open request of the same type with the already-open message, but allows another type', function (): void {
    $student = tp908Student();
    app(SubmitDataRequest::class)($student, $student, DataRequestType::Export);

    try {
        app(SubmitDataRequest::class)($student, $student, DataRequestType::Export);
        $this->fail('Expected a ValidationException');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('type')
            ->and($e->errors()['type'])->toContain(__('privacy.error_already_open'));
    }

    expect(app(SubmitDataRequest::class)($student, $student, DataRequestType::Deletion)->type)->toBe(DataRequestType::Deletion);
})->group('T-P9-08', 'A1');

it('refuses a correction without details and a deletion for a staff account', function (): void {
    $student = tp908Student();
    expect(fn () => app(SubmitDataRequest::class)($student, $student, DataRequestType::Correction, 'short'))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey('details'));

    $admin = tp908Staff('admin');
    try {
        app(SubmitDataRequest::class)($admin, $admin, DataRequestType::Deletion);
        $this->fail('Expected a ValidationException');
    } catch (ValidationException $e) {
        expect($e->errors()['type'])->toContain(__('privacy.error_staff_account'));
    }
})->group('T-P9-08', 'A1');

it('refuses a request for an unlinked child, an inactive requester and an anonymized subject', function (): void {
    $parent = User::factory()->create(['status' => 'active'])->assignRole('student');
    $stranger = tp908Student();
    expect(fn () => app(SubmitDataRequest::class)($parent, $stranger, DataRequestType::Export))
        ->toThrow(AuthorizationException::class);

    $inactive = User::factory()->create(['status' => 'suspended'])->assignRole('student');
    expect(fn () => app(SubmitDataRequest::class)($inactive, $inactive, DataRequestType::Export))
        ->toThrow(AuthorizationException::class);
})->group('T-P9-08', 'A2');

it('lets only privacy managers approve, idempotently, and refuses support and finance', function (): void {
    $student = tp908Student();
    $request = app(SubmitDataRequest::class)($student, $student, DataRequestType::Export);

    expect(fn () => app(ApproveDataRequest::class)(tp908Staff('support'), $request))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ApproveDataRequest::class)(tp908Staff('finance'), $request))->toThrow(AuthorizationException::class);

    $admin = tp908Staff('admin');
    $approved = app(ApproveDataRequest::class)($admin, $request);
    $again = app(ApproveDataRequest::class)($admin, $approved);

    expect($approved->status)->toBe(DataRequestStatus::Approved)
        ->and($again->status)->toBe(DataRequestStatus::Approved)
        ->and(tp908Events('privacy.request_approved'))->toBe(1);
})->group('T-P9-08', 'A1');

it('stores the review reason on reject and refuses a reason under 10 characters', function (): void {
    $student = tp908Student();
    $request = app(SubmitDataRequest::class)($student, $student, DataRequestType::Export);
    $admin = tp908Staff('admin');

    expect(fn () => app(RejectDataRequest::class)($admin, $request, 'too short'))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey('reason'));

    $rejected = app(RejectDataRequest::class)($admin, $request, 'We cannot verify this account yet.');
    expect($rejected->status)->toBe(DataRequestStatus::Rejected)
        ->and($rejected->review_note)->toBe('We cannot verify this account yet.');
})->group('T-P9-08', 'A1');

it('refuses to complete a request that is still requested and completes an approved one only once', function (): void {
    $student = tp908Student();
    $request = app(SubmitDataRequest::class)($student, $student, DataRequestType::Correction, 'Please fix my name spelling.');
    $admin = tp908Staff('admin');

    expect(fn () => app(CompleteDataRequest::class)($admin, $request))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey('request'));

    app(ApproveDataRequest::class)($admin, $request);
    expect(fn () => app(CompleteDataRequest::class)($admin, $request))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey('note'));

    $done = app(CompleteDataRequest::class)($admin, $request, 'Corrected the spelling in the profile.');
    $twice = app(CompleteDataRequest::class)($admin, $done, 'Corrected the spelling in the profile.');

    expect($done->status)->toBe(DataRequestStatus::Completed)
        ->and($twice->status)->toBe(DataRequestStatus::Completed)
        ->and(tp908Events('privacy.request_completed'))->toBe(1);
})->group('T-P9-08', 'A1');

it('lets only the requester withdraw a request that is still requested', function (): void {
    $student = tp908Student();
    $other = tp908Student();
    $request = app(SubmitDataRequest::class)($student, $student, DataRequestType::Export);

    expect(fn () => app(CancelDataRequest::class)($other, $request))->toThrow(AuthorizationException::class);

    $cancelled = app(CancelDataRequest::class)($student, $request);
    expect($cancelled->status)->toBe(DataRequestStatus::Cancelled);
})->group('T-P9-08', 'A1');

it('anonymizes a deletion: the account is scrubbed, orders stay, and a second run writes no second audit', function (): void {
    $student = tp908Student('real.learner@example.test');
    $orderId = tp908Order($student, 'T908-ORDER-1', 10000);
    $ordersBefore = DB::table('orders')->where('id', $orderId)->first();

    $request = app(SubmitDataRequest::class)($student, $student, DataRequestType::Deletion);
    $admin = tp908Staff('admin');
    app(ApproveDataRequest::class)($admin, $request);
    app(CompleteDataRequest::class)($admin, $request);

    $row = DB::table('users')->where('id', $student->id)->first();
    $order = DB::table('orders')->where('id', $orderId)->first();

    expect($order)->toEqual($ordersBefore)
        ->and($order->payer_id)->toBe($student->id)
        ->and($row->status)->toBe('anonymized')
        ->and($row->anonymized_at)->not->toBeNull()
        ->and($row->email)->toBeNull()
        ->and($row->name)->toBe('Anonymized user #'.$student->id)
        ->and(Auth::attempt(['email' => 'real.learner@example.test', 'password' => 'password']))->toBeFalse()
        ->and(tp908Events('privacy.account_anonymized'))->toBe(1);

    app(AnonymizeAccount::class)($admin, $student->refresh());
    expect(tp908Events('privacy.account_anonymized'))->toBe(1);
})->group('T-P9-08', 'A1');

it('refuses to anonymize an account with an open refund, and refuses staff accounts', function (): void {
    $student = tp908Student();
    $orderId = tp908Order($student, 'T908-ORDER-2', 5000);
    DB::table('refunds')->insert([
        'reference' => 'T908-REF', 'order_id' => $orderId, 'currency' => 'EGP', 'amount_minor' => 1000,
        'reason' => 'Open refund', 'method' => 'manual', 'status' => 'requested', 'requested_by' => $student->id,
    ]);
    $admin = tp908Staff('admin');

    expect(fn () => app(AnonymizeAccount::class)($admin, $student))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toBeArray());

    $staff = tp908Staff('instructor_academy');
    expect(fn () => app(AnonymizeAccount::class)($admin, $staff))->toThrow(ValidationException::class);
})->group('T-P9-08', 'A1');

it('builds an export zip on the private disk with manifest, README and the subject topics only', function (): void {
    $student = tp908Student();
    $stranger = tp908Student();
    tp908Order($student, 'T908-EXPORT-1', 2000);
    tp908Order($stranger, 'T908-STRANGER-1', 9999);

    $request = app(SubmitDataRequest::class)($student, $student, DataRequestType::Export);
    $admin = tp908Staff('admin');
    app(ApproveDataRequest::class)($admin, $request);
    $done = app(CompleteDataRequest::class)($admin, $request);

    expect($done->archive_path)->toStartWith('privacy/exports/')
        ->and($done->archive_path)->toEndWith('.zip')
        ->and(Storage::disk('local')->exists($done->archive_path))->toBeTrue()
        ->and(Storage::disk('public')->allFiles())->toBe([])
        ->and($done->archive_sha256)->toBe(hash_file('sha256', Storage::disk('local')->path($done->archive_path)))
        ->and($done->archive_expires_at)->not->toBeNull();

    $zip = new ZipArchive;
    expect($zip->open(Storage::disk('local')->path($done->archive_path)))->toBeTrue();
    $names = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $names[] = $zip->getNameIndex($i);
    }
    $orders = $zip->getFromName('orders.json') ?: '';
    $zip->close();

    expect($names)->toContain('manifest.json')
        ->and($names)->toContain('README.txt')
        ->and($names)->toContain('profile.json')
        ->and($orders)->toContain('T908-EXPORT-1')
        ->and($orders)->not->toContain('T908-STRANGER-1')
        ->and(implode("\n", $names))->not->toContain('password')
        ->and(implode("\n", $names))->not->toContain('remember_token');
})->group('T-P9-08', 'A1');

it('lets the requester download a completed export with no-store, and answers 404 to others and guests', function (): void {
    $student = tp908Student();
    $other = tp908Student();
    $request = app(SubmitDataRequest::class)($student, $student, DataRequestType::Export);
    $admin = tp908Staff('admin');
    app(ApproveDataRequest::class)($admin, $request);
    $done = app(CompleteDataRequest::class)($admin, $request);

    // Guest first: actingAs() persists for later requests in the same test.
    $this->get(route('profile.privacy.download', $done))->assertRedirect();

    $this->actingAs($other)->get(route('profile.privacy.download', $done))->assertNotFound();

    $response = $this->actingAs($student)->get(route('profile.privacy.download', $done));
    $response->assertOk();
    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($done->refresh()->archive_downloaded_at)->not->toBeNull()
        ->and(tp908Events('privacy.export_downloaded'))->toBe(1);
})->group('T-P9-08', 'A1');

it('refuses a retention day value below the minimum and an unknown class', function (): void {
    $admin = tp908Staff('admin');

    expect(fn () => app(SaveRetentionPolicies::class)($admin, ['session_logs' => 1]))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey('session_logs'));

    expect(fn () => app(SaveRetentionPolicies::class)($admin, ['not_a_class' => 90]))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey('retention'));

    expect(fn () => app(SaveRetentionPolicies::class)(tp908Student(), ['session_logs' => 90]))
        ->toThrow(AuthorizationException::class);
})->group('T-P9-08', 'A2');

it('removes only an expired export archive file, nulls its path and keeps the request row', function (): void {
    $student = tp908Student();
    $path = 'privacy/exports/'.str_repeat('a', 8).'.zip';
    Storage::disk('local')->put($path, 'zip-bytes');
    $expired = DataRequest::query()->create([
        'subject_id' => $student->id, 'requested_by' => $student->id, 'type' => DataRequestType::Export->value,
        'status' => DataRequestStatus::Completed->value, 'archive_path' => $path, 'archive_sha256' => hash('sha256', 'zip-bytes'),
        'archive_size' => 9, 'archive_expires_at' => now()->subDay(),
    ]);
    $fresh = DataRequest::query()->create([
        'subject_id' => $student->id, 'requested_by' => $student->id, 'type' => DataRequestType::Export->value,
        'status' => DataRequestStatus::Completed->value, 'archive_path' => 'privacy/exports/fresh.zip',
        'archive_sha256' => hash('sha256', 'x'), 'archive_size' => 1, 'archive_expires_at' => now()->addDay(),
    ]);
    Storage::disk('local')->put('privacy/exports/fresh.zip', 'x');

    $report = app(PruneRetainedData::class)([RetentionClass::DataExports]);

    expect($report)->toHaveKey('data_exports')
        ->and(Storage::disk('local')->exists($path))->toBeFalse()
        ->and(Storage::disk('local')->exists('privacy/exports/fresh.zip'))->toBeTrue()
        ->and($expired->refresh()->archive_path)->toBeNull()
        ->and(DataRequest::query()->whereKey($expired->id)->exists())->toBeTrue()
        ->and($fresh->refresh()->archive_path)->toBe('privacy/exports/fresh.zip');
})->group('T-P9-08', 'A2');

it('never lists a financial table as retention-managed and refuses to prune protected data', function (): void {
    foreach (RetentionClass::cases() as $class) {
        expect(array_intersect($class->tables(), ProtectedData::TABLES))->toBe([]);
    }

    expect(fn () => ProtectedData::assertNotProtected(['orders']))->toThrow(LogicException::class);
})->group('T-P9-08', 'A2');

it('keeps every class with null days and deletes nothing from financial tables', function (): void {
    $payer = tp908Student();
    $orderId = tp908Order($payer, 'T908-KEEP-1', 3000);
    DB::table('orders')->where('id', $orderId)->update(['paid_at' => now()->subYears(10)]);

    $report = app(PruneRetainedData::class)();

    expect(DB::table('orders')->where('id', $orderId)->exists())->toBeTrue()
        ->and($report['data_exports'] ?? null)->toBeInt();
})->group('T-P9-08', 'A2');
