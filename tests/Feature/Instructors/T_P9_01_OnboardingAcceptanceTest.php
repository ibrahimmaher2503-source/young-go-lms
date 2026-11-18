<?php

// T-P9-01 | YG-GAP-INS-001 YG-GAP-INS-002 YG-GAP-INS-003 | A1 A2

declare(strict_types=1);

use App\Domain\Instructors\Actions\AcceptInstructorAgreement;
use App\Domain\Instructors\Actions\ApproveInstructorApplication;
use App\Domain\Instructors\Actions\PublishInstructorAgreement;
use App\Domain\Instructors\Actions\RejectInstructorApplication;
use App\Domain\Instructors\Actions\RevealPayoutProfile;
use App\Domain\Instructors\Actions\SaveInstructorAgreement;
use App\Domain\Instructors\Actions\SavePayoutProfile;
use App\Domain\Instructors\Actions\SubmitInstructorApplication;
use App\Domain\Instructors\Events\InstructorApplicationReviewed;
use App\Domain\Instructors\Models\InstructorAgreement;
use App\Domain\Instructors\Models\InstructorApplication;
use App\Domain\Instructors\Models\PayoutProfile;
use App\Domain\Instructors\PayoutMask;
use App\Domain\Instructors\Queries\InstructorAgreements;
use App\Domain\Instructors\Queries\PayoutReadiness;
use App\Models\Course;
use App\Models\InstructorProfile;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

function tp901Applicant(array $overrides = []): User
{
    return User::factory()->create(array_merge(['status' => 'active', 'email_verified_at' => now()], $overrides))->assignRole('student');
}

function tp901Data(array $overrides = []): array
{
    return array_merge([
        'full_name' => 'Salma El-Masry',
        'phone' => '+201012345678',
        'discipline' => 'Watercolor Illustration',
        'experience_years' => 6,
        'bio' => 'Teaching creative watercolor techniques and foundational sketching for young artists.',
        'portfolio_links' => ['https://portfolio.example.com/salma-art'],
    ], $overrides);
}

function tp901Submit(User $applicant, array $overrides = [], ?UploadedFile $file = null): InstructorApplication
{
    return app(SubmitInstructorApplication::class)($applicant, tp901Data($overrides), $file ?? UploadedFile::fake()->image('id.png', 20, 20), 'en');
}

function tp901Agreement(string $version, string $suffix = ''): InstructorAgreement
{
    return app(SaveInstructorAgreement::class)(tp901Admin(), null, [
        'version' => $version,
        'title' => ['ar' => 'اتفاقية المدرس'.$suffix, 'en' => 'Instructor agreement'.$suffix],
        'body' => ['ar' => 'نص الاتفاقية', 'en' => 'Agreement text'.$suffix],
    ]);
}

function tp901Admin(): User
{
    return User::factory()->create(['status' => 'active'])->assignRole('admin');
}

it('stores a pending application with the ID on the private disk and audits it without personal values', function (): void {
    Storage::fake(config('media.disk'));
    $applicant = tp901Applicant();

    $application = tp901Submit($applicant);

    expect($application->status->value)->toBe('pending')
        ->and($application->pending_user_id)->toBe($applicant->id)
        ->and($application->id_document_path)->toStartWith(Config::string('instructor_onboarding.id_document_directory'));

    $disk = Storage::disk(config('media.disk'));
    expect($disk->exists($application->id_document_path))->toBeTrue()
        ->and($application->id_document_path)->not->toContain('id.png');

    $audit = Activity::query()->where('event', 'instructor_application.submitted')->first();
    expect($audit)->not->toBeNull()
        ->and(json_encode($audit->properties))->not->toContain('Salma')
        ->and(json_encode($audit->properties))->not->toContain('+201012345678')
        ->and(json_encode($audit->properties))->not->toContain('portfolio.example.com');
})->group('T-P9-01', 'YG-GAP-INS-001');

it('refuses unverified, child, already-instructor and second pending applications with the application key', function (): void {
    Storage::fake(config('media.disk'));
    $unverified = tp901Applicant(['email_verified_at' => null]);
    $child = User::factory()->create(['status' => 'active', 'email' => null, 'email_verified_at' => null])->assignRole('student');
    $instructor = User::factory()->create(['status' => 'active', 'email_verified_at' => now()])->assignRole('instructor_independent');

    foreach ([$unverified, $child, $instructor] as $user) {
        expect(fn () => tp901Submit($user))->toThrow(ValidationException::class);
    }

    $applicant = tp901Applicant();
    tp901Submit($applicant);

    try {
        tp901Submit($applicant);
        $this->fail('Second pending application was accepted.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('application');
    }

    expect(InstructorApplication::query()->count())->toBe(1);
})->group('T-P9-01', 'YG-GAP-INS-001');

it('enforces one pending application per account at the database level', function (): void {
    $applicant = tp901Applicant();
    $admin = tp901Admin();
    $first = InstructorApplication::query()->create([
        'user_id' => $applicant->id, 'pending_user_id' => $applicant->id, 'full_name' => 'A B', 'discipline' => 'Art', 'bio' => str_repeat('x', 40),
        'portfolio_links' => ['https://example.com'], 'id_document_path' => 'instructor-applications/a', 'id_document_mime' => 'image/png',
        'id_document_size' => 10, 'id_document_sha256' => str_repeat('a', 64), 'status' => 'pending',
    ]);

    expect(fn () => InstructorApplication::query()->create([
        'user_id' => $applicant->id, 'pending_user_id' => $applicant->id, 'full_name' => 'A B', 'discipline' => 'Art', 'bio' => str_repeat('x', 40),
        'portfolio_links' => ['https://example.com'], 'id_document_path' => 'instructor-applications/b', 'id_document_mime' => 'image/png',
        'id_document_size' => 10, 'id_document_sha256' => str_repeat('b', 64), 'status' => 'pending',
    ]))->toThrow(UniqueConstraintViolationException::class);

    expect($first->exists)->toBeTrue()
        ->and($admin->id)->not->toBeNull();
})->group('T-P9-01', 'YG-GAP-INS-001');

it('refuses invalid links, spoofed ID binaries and bad names, leaving no file behind', function (): void {
    Storage::fake(config('media.disk'));
    $applicant = tp901Applicant();
    $directory = Config::string('instructor_onboarding.id_document_directory');

    expect(fn () => tp901Submit($applicant, ['portfolio_links' => ['javascript:alert(1)']]))->toThrow(ValidationException::class)
        ->and(fn () => tp901Submit($applicant, ['portfolio_links' => []]))->toThrow(ValidationException::class)
        ->and(fn () => tp901Submit($applicant, ['phone' => '12']))->toThrow(ValidationException::class)
        ->and(fn () => tp901Submit($applicant, ['experience_years' => 99]))->toThrow(ValidationException::class)
        ->and(fn () => tp901Submit($applicant, ['full_name' => 'S']))->toThrow(ValidationException::class)
        ->and(fn () => tp901Submit($applicant, [], UploadedFile::fake()->createWithContent('id.png', "MZ\x90\x00executable")))->toThrow(ValidationException::class);

    expect(Storage::disk(config('media.disk'))->allFiles($directory))->toBeEmpty()
        ->and(InstructorApplication::query()->count())->toBe(0);
})->group('T-P9-01', 'YG-GAP-INS-001');

it('approves an application through the action: role kept, draft profile hidden, audits and event once', function (): void {
    Storage::fake(config('media.disk'));
    Event::fake([InstructorApplicationReviewed::class]);
    $applicant = tp901Applicant();
    $application = tp901Submit($applicant);
    $admin = tp901Admin();

    $approved = app(ApproveInstructorApplication::class)($admin, $application);

    expect($approved->status->value)->toBe('approved')
        ->and($approved->pending_user_id)->toBeNull()
        ->and($approved->reviewed_by)->toBe($admin->id)
        ->and($applicant->fresh()->hasRole('instructor_independent'))->toBeTrue()
        ->and($applicant->fresh()->hasRole('student'))->toBeTrue();

    $profile = InstructorProfile::query()->where('user_id', $applicant->id)->firstOrFail();
    expect($profile->status)->toBe(InstructorProfile::DRAFT);

    expect(Activity::query()->where('event', 'user.roles_changed')->count())->toBe(1);

    // Approving again is a no-op: no duplicate audit, no duplicate profile, no second event
    app(ApproveInstructorApplication::class)($admin, $application);
    expect(Activity::query()->where('event', 'user.roles_changed')->count())->toBe(1)
        ->and(InstructorProfile::query()->where('user_id', $applicant->id)->count())->toBe(1);

    Event::assertDispatchedTimes(InstructorApplicationReviewed::class, 1);
})->group('T-P9-01', 'YG-GAP-INS-001');

it('rejects with a reason, frees the pending slot for a new application, and refuses cross-state decisions', function (): void {
    Storage::fake(config('media.disk'));
    $applicant = tp901Applicant();
    $application = tp901Submit($applicant);
    $admin = tp901Admin();

    expect(fn () => app(RejectInstructorApplication::class)($admin, $application, 'short'))->toThrow(ValidationException::class);

    $rejected = app(RejectInstructorApplication::class)($admin, $application, 'Portfolio links did not open for review.');
    expect($rejected->status->value)->toBe('rejected')
        ->and($rejected->pending_user_id)->toBeNull()
        ->and($applicant->fresh()->hasRole('instructor_independent'))->toBeFalse();

    $second = tp901Submit($applicant);
    expect($second->status->value)->toBe('pending');

    $approved = app(ApproveInstructorApplication::class)($admin, $second);
    expect(fn () => app(RejectInstructorApplication::class)($admin, $approved, 'Changed my mind about this.'))->toThrow(ValidationException::class);
})->group('T-P9-01', 'YG-GAP-INS-001');

it('denies application review to finance and instructors without instructor.manage', function (): void {
    Storage::fake(config('media.disk'));
    $application = tp901Submit(tp901Applicant());
    $finance = User::factory()->create(['status' => 'active'])->assignRole('finance');
    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_independent');

    foreach ([$finance, $instructor] as $actor) {
        expect(fn () => app(ApproveInstructorApplication::class)($actor, $application))->toThrow(AuthorizationException::class)
            ->and(fn () => app(RejectInstructorApplication::class)($actor, $application, 'A valid reason here.'))->toThrow(AuthorizationException::class);
    }
    expect($application->fresh()->status->value)->toBe('pending');
})->group('T-P9-01', 'YG-GAP-INS-001');

it('creates and edits draft agreements in AR and EN, locks published versions and publishes idempotently', function (): void {
    $admin = tp901Admin();
    $draft = tp901Agreement('ins-v1');
    expect($draft->status)->toBe(InstructorAgreement::DRAFT);

    expect(fn () => app(SaveInstructorAgreement::class)($admin, null, ['version' => 'ins-v2', 'title' => ['ar' => 'عنوان'], 'body' => ['en' => 'x']]))->toThrow(ValidationException::class)
        ->and(fn () => app(SaveInstructorAgreement::class)($admin, null, ['version' => 'Bad Version!', 'title' => ['ar' => 'ع', 'en' => 'T'], 'body' => ['ar' => 'ن', 'en' => 'B']]))->toThrow(ValidationException::class)
        ->and(fn () => app(SaveInstructorAgreement::class)($admin, null, ['version' => 'ins-v1', 'title' => ['ar' => 'ع', 'en' => 'T'], 'body' => ['ar' => 'ن', 'en' => 'B']]))->toThrow(ValidationException::class);

    $edited = app(SaveInstructorAgreement::class)($admin, $draft, ['title' => ['ar' => 'عنوان جديد', 'en' => 'New title'], 'body' => ['ar' => 'ن', 'en' => 'Body']]);
    expect($edited->getTranslation('title', 'en'))->toBe('New title');

    $published = app(PublishInstructorAgreement::class)($admin, $edited);
    expect($published->isPublished())->toBeTrue();
    expect(fn () => app(SaveInstructorAgreement::class)($admin, $published, ['title' => ['ar' => 'ع', 'en' => 'T'], 'body' => ['ar' => 'ن', 'en' => 'B']]))->toThrow(ValidationException::class);

    app(PublishInstructorAgreement::class)($admin, $published);
    expect(Activity::query()->where('event', 'instructor_agreement.published')->count())->toBe(1);
})->group('T-P9-01', 'YG-GAP-INS-002');

it('blocks independent instructors from creating courses until they accept the newest published version (A2)', function (): void {
    $admin = tp901Admin();
    $independent = User::factory()->create(['status' => 'active'])->assignRole('instructor_independent');
    $academy = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');

    // No published version: nobody is blocked
    expect(InstructorAgreements::requiresAcceptance($independent))->toBeFalse();

    $v1 = app(PublishInstructorAgreement::class)($admin, tp901Agreement('ins-a2-v1'));
    expect(InstructorAgreements::requiresAcceptance($independent))->toBeTrue()
        ->and(InstructorAgreements::requiresAcceptance($academy))->toBeFalse()
        ->and(InstructorAgreements::requiresAcceptance($admin))->toBeFalse()
        ->and($independent->can('create', Course::class))->toBeFalse();

    app(AcceptInstructorAgreement::class)($independent, $v1, '203.0.113.9');
    expect(InstructorAgreements::requiresAcceptance($independent))->toBeFalse()
        ->and($independent->can('create', Course::class))->toBeTrue();

    $v2 = app(PublishInstructorAgreement::class)($admin, tp901Agreement('ins-a2-v2', ' v2'));
    expect(InstructorAgreements::current()->id)->toBe($v2->id)
        ->and(InstructorAgreements::requiresAcceptance($independent))->toBeTrue();
})->group('T-P9-01', 'A2');

it('stores the accepted version, time and IP, treats an invalid IP as null and is idempotent', function (): void {
    $admin = tp901Admin();
    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_independent');
    $agreement = app(PublishInstructorAgreement::class)($admin, tp901Agreement('ins-acc-v1'));

    $first = app(AcceptInstructorAgreement::class)($instructor, $agreement, '198.51.100.4');
    $again = app(AcceptInstructorAgreement::class)($instructor, $agreement, '198.51.100.4');

    expect($first->id)->toBe($again->id)
        ->and($first->version)->toBe('ins-acc-v1')
        ->and($first->ip)->toBe('198.51.100.4')
        ->and($first->accepted_at)->not->toBeNull();
    expect(DB::table('instructor_agreement_acceptances')->where('user_id', $instructor->id)->count())->toBe(1);
    expect(Activity::query()->where('event', 'instructor_agreement.accepted')->count())->toBe(1);

    $other = User::factory()->create(['status' => 'active'])->assignRole('instructor_independent');
    $bad = app(AcceptInstructorAgreement::class)($other, $agreement, 'not-an-ip');
    expect($bad->ip)->toBeNull();

    $student = User::factory()->create(['status' => 'active'])->assignRole('student');
    expect(fn () => app(AcceptInstructorAgreement::class)($student, $agreement, '198.51.100.4'))->toThrow(AuthorizationException::class);
})->group('T-P9-01', 'YG-GAP-INS-002');

it('encrypts payout details at rest, hides them from serialization and validates each method', function (): void {
    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_independent');
    $save = app(SavePayoutProfile::class);

    $profile = $save($instructor, ['method' => 'bank', 'holder_name' => 'Salma El-Masry', 'bank_name' => 'National Bank', 'account_number' => 'eg12 3456-7890 1234 5678']);

    $raw = (string) DB::table('instructor_payout_profiles')->value('details');
    expect($raw)->not->toContain('Salma')
        ->and($raw)->not->toContain('National Bank')
        ->and($raw)->not->toContain('EG123456789012345678');
    expect($profile->fresh()->details['account_number'])->toBe('EG123456789012345678')
        ->and($profile->toArray())->not->toHaveKey('details')
        ->and($profile->toJson())->not->toContain('EG123456789012345678');

    expect(fn () => $save($instructor, ['method' => 'bank', 'holder_name' => 'Salma', 'bank_name' => 'NB', 'account_number' => '123']))->toThrow(ValidationException::class)
        ->and(fn () => $save($instructor, ['method' => 'wallet', 'holder_name' => 'Salma', 'wallet_number' => 'abc']))->toThrow(ValidationException::class);

    $wallet = $save($instructor, ['method' => 'wallet', 'holder_name' => 'Salma El-Masry', 'wallet_number' => '+20 101 234 5678']);
    expect($wallet->method)->toBe(PayoutProfile::WALLET)
        ->and($wallet->fresh()->details)->toBe(['holder_name' => 'Salma El-Masry', 'wallet_number' => '+201012345678']);
    expect(DB::table('instructor_payout_profiles')->count())->toBe(1);

    $student = User::factory()->create(['status' => 'active'])->assignRole('student');
    expect(fn () => $save($student, ['method' => 'wallet', 'holder_name' => 'Salma', 'wallet_number' => '01012345678']))->toThrow(AuthorizationException::class);

    $auditText = json_encode(Activity::query()->where('event', 'like', 'payout_profile.%')->get()->map->properties->all());
    expect($auditText)->not->toContain('Salma')
        ->and($auditText)->not->toContain('+201012345678')
        ->and($auditText)->not->toContain('EG123456789012345678');
})->group('T-P9-01', 'YG-GAP-INS-003');

it('reveals details only to finance with reveal permission and audits the reveal before returning', function (): void {
    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_independent');
    $profile = app(SavePayoutProfile::class)($instructor, ['method' => 'wallet', 'holder_name' => 'Salma El-Masry', 'wallet_number' => '01012345678']);
    $finance = User::factory()->create(['status' => 'active'])->assignRole('finance');
    $admin = tp901Admin();

    expect(fn () => app(RevealPayoutProfile::class)($admin, $profile))->toThrow(AuthorizationException::class)
        ->and(fn () => app(RevealPayoutProfile::class)($instructor, $profile))->toThrow(AuthorizationException::class);

    expect(Activity::query()->where('event', 'payout_profile.revealed')->count())->toBe(0);

    $details = app(RevealPayoutProfile::class)($finance, $profile);
    expect($details['wallet_number'])->toBe('01012345678');

    $audit = Activity::query()->where('event', 'payout_profile.revealed')->firstOrFail();
    expect(json_encode($audit->properties))->not->toContain('01012345678')
        ->and(json_encode($audit->properties))->not->toContain('Salma');
})->group('T-P9-01', 'YG-GAP-INS-003');

it('masks identifiers to the last four characters and tracks readiness per profile', function (): void {
    expect(PayoutMask::value('EG123456789012345678'))->toEndWith('5678')
        ->and(PayoutMask::value('EG123456789012345678'))->not->toContain('EG1234')
        ->and(PayoutMask::value('12'))->toBe('****12')
        ->and(PayoutMask::value(null))->toBe('');

    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_independent');
    expect(PayoutReadiness::isReady($instructor))->toBeFalse();

    app(SavePayoutProfile::class)($instructor, ['method' => 'wallet', 'holder_name' => 'Salma El-Masry', 'wallet_number' => '01012345678']);
    expect(PayoutReadiness::isReady($instructor))->toBeTrue();
})->group('T-P9-01', 'YG-GAP-INS-003');

it('grants payout permissions to finance only (super admin excepted) through the role seeder', function (): void {
    $finance = User::factory()->create(['status' => 'active'])->assignRole('finance');
    $admin = tp901Admin();
    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_independent');

    expect($finance->can('payout_profile.view'))->toBeTrue()
        ->and($finance->can('payout_profile.reveal'))->toBeTrue()
        ->and($admin->can('payout_profile.view'))->toBeFalse()
        ->and($instructor->can('payout_profile.view'))->toBeFalse();
})->group('T-P9-01', 'YG-GAP-INS-003');
