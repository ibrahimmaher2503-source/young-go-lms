<?php

declare(strict_types=1);

use App\Domain\Identity\ActingLearner;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\Instructors\Actions\AcceptInstructorAgreement;
use App\Domain\Instructors\Actions\ApproveInstructorApplication;
use App\Domain\Instructors\Actions\PublishInstructorAgreement;
use App\Domain\Instructors\Actions\RejectInstructorApplication;
use App\Domain\Instructors\Actions\RevealPayoutProfile;
use App\Domain\Instructors\Actions\SaveInstructorAgreement;
use App\Domain\Instructors\Actions\SavePayoutProfile;
use App\Domain\Instructors\Actions\SubmitInstructorApplication;
use App\Domain\Instructors\ApplicationStatus;
use App\Domain\Instructors\Events\InstructorAgreementAccepted;
use App\Domain\Instructors\Events\InstructorApplicationReviewed;
use App\Domain\Instructors\Models\InstructorAgreementAcceptance;
use App\Domain\Instructors\Models\InstructorApplication;
use App\Domain\Instructors\Models\PayoutProfile;
use App\Domain\Instructors\PayoutMask;
use App\Domain\Instructors\Queries\InstructorAgreements;
use App\Domain\Instructors\Queries\PayoutReadiness;
use App\Domain\Instructors\Queries\PublicInstructors;
use App\Filament\Pages\InstructorAgreementPage;
use App\Filament\Pages\MyPayoutProfile;
use App\Filament\Resources\InstructorApplications\Pages\ListInstructorApplications;
use App\Filament\Resources\PayoutProfiles\Pages\ListPayoutProfiles;
use App\Models\Course;
use App\Models\InstructorProfile;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

function ioValidApplicationPayload(): array
{
    return [
        'full_name' => 'Salma El-Masry',
        'phone' => '+201012345678',
        'discipline' => 'Watercolor Illustration',
        'experience_years' => 6,
        'bio' => 'Teaching creative watercolor techniques and foundational sketching for young artists for six years.',
        'portfolio_links' => ['https://portfolio.example.com/salma-art'],
    ];
}

it('handles teach page states, private ID validation/download, honeypot, and admin approve/reject transitions', function (): void {
    Storage::fake(config('media.disk'));
    Event::fake([InstructorApplicationReviewed::class]);

    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $finance = User::factory()->create(['status' => 'active'])->assignRole('finance');
    $support = User::factory()->create(['status' => 'active'])->assignRole('support');
    $applicant = User::factory()->create(['status' => 'active', 'email_verified_at' => now()])->assignRole('student');
    $unverified = User::factory()->create(['status' => 'active', 'email_verified_at' => null])->assignRole('student');
    $child = User::factory()->create(['status' => 'active', 'email' => null, 'email_verified_at' => null])->assignRole('student');
    $parent = User::factory()->create(['status' => 'active', 'email_verified_at' => now()])->assignRole('parent');
    $alreadyInstructor = User::factory()->create(['status' => 'active', 'email_verified_at' => now()])->assignRole('instructor_independent');

    GuardianChildLink::query()->create([
        'guardian_id' => $parent->id,
        'child_id' => $child->id,
        'status' => GuardianChildLink::STATUS_VERIFIED,
        'method' => GuardianChildLink::METHOD_CREATED_BY_PARENT,
        'verified_at' => now(),
    ]);

    // Guest sees teach CTAs in AR/EN and cannot submit
    $this->withSession(['locale' => 'en'])->get(route('instructors.teach'))->assertOk()->assertSee('data-teach-state="guest"', false);
    $this->withSession(['locale' => 'ar'])->get(route('instructors.teach'))->assertOk()->assertSee('dir="rtl"', false)->assertSee('data-teach-state="guest"', false);
    $this->post(route('instructors.apply'), [])->assertRedirect(route('login.page'));

    // Non-eligible states see refusals and Action rejects them without leaving files behind
    $validPng = UploadedFile::fake()->image('id.png', 20, 20);
    $this->actingAs($unverified)->get(route('instructors.teach'))->assertOk()->assertSee('data-teach-state="unverified"', false);
    $this->actingAs($child)->get(route('instructors.teach'))->assertOk()->assertSee('data-teach-state="ineligible"', false);

    $submit = app(SubmitInstructorApplication::class);
    expect(fn () => $submit($unverified, ioValidApplicationPayload(), $validPng, 'en'))->toThrow(ValidationException::class)
        ->and(fn () => $submit($child, ioValidApplicationPayload(), $validPng, 'en'))->toThrow(ValidationException::class)
        ->and(fn () => $submit($alreadyInstructor, ioValidApplicationPayload(), $validPng, 'en'))->toThrow(ValidationException::class);

    // Parent acting in child context is also ineligible
    $this->actingAs($parent);
    $this->withSession([ActingLearner::SESSION_KEY => $child->id])->get(route('instructors.teach'))->assertOk()->assertSee('data-teach-state="ineligible"', false);
    app(ActingLearner::class)->enter($child);
    expect(fn () => $submit($parent, ioValidApplicationPayload(), $validPng, 'en'))->toThrow(ValidationException::class);
    app(ActingLearner::class)->exit();

    // Bad link / spoofed binary / missing privacy consent leave no orphan private files
    $spoofed = UploadedFile::fake()->createWithContent('id.png', "MZ\x90\x00executable");
    expect(fn () => $submit($applicant, ioValidApplicationPayload(), $spoofed, 'en'))->toThrow(ValidationException::class)
        ->and(fn () => $submit($applicant, array_merge(ioValidApplicationPayload(), ['portfolio_links' => ['javascript:alert(1)']]), $validPng, 'en'))->toThrow(ValidationException::class)
        ->and(Storage::disk(config('media.disk'))->allFiles(Config::string('instructor_onboarding.id_document_directory')))->toBeEmpty();

    $this->actingAs($applicant)->post(route('instructors.apply'), [
        'full_name' => 'Salma El-Masry',
        'discipline' => 'Watercolor',
        'bio' => str_repeat('Watercolor portfolio notes. ', 3),
        'portfolio_links' => 'https://portfolio.example.com/salma-art',
        'id_document' => $validPng,
    ])->assertSessionHasErrors('privacy');

    // Honeypot stores nothing and flashes notice
    $honeypot = Config::string('contact.honeypot_field');
    $this->actingAs($applicant)->post(route('instructors.apply'), [$honeypot => 'https://spam.example'])
        ->assertRedirect(route('instructors.teach'))
        ->assertSessionHas('teach_notice');
    expect(InstructorApplication::query()->count())->toBe(0);

    // Verified adult submits valid HTTP application
    $this->actingAs($applicant)->post(route('instructors.apply'), [
        'full_name' => 'Salma El-Masry',
        'phone' => '+201012345678',
        'discipline' => 'Watercolor Illustration',
        'experience_years' => 6,
        'bio' => 'Teaching creative watercolor techniques and foundational sketching for young artists for six years.',
        'portfolio_links' => 'https://portfolio.example.com/salma-art',
        'id_document' => UploadedFile::fake()->image('national-id.png', 20, 20),
        'privacy' => '1',
    ])->assertRedirect(route('instructors.teach'))->assertSessionHas('teach_notice');

    $application = InstructorApplication::query()->sole();
    expect($application->status)->toBe(ApplicationStatus::Pending)
        ->and($application->pending_user_id)->toBe($applicant->id)
        ->and(str_starts_with($application->id_document_path, 'instructor-applications/'))->toBeTrue()
        ->and(Storage::disk(config('media.disk'))->exists($application->id_document_path))->toBeTrue();

    // Submitted audit excludes name, phone, and links
    $submittedLog = Activity::query()->where('event', 'instructor_application.submitted')->latest('id')->first();
    expect($submittedLog)->not->toBeNull();
    $propsJson = json_encode($submittedLog->properties);
    expect($propsJson)->not->toContain('Salma El-Masry')
        ->not->toContain('01012345678')
        ->not->toContain('portfolio.example.com');

    // Duplicate pending application refused
    expect(fn () => $submit($applicant, ioValidApplicationPayload(), UploadedFile::fake()->image('dup.png', 16, 16), 'en'))
        ->toThrow(ValidationException::class);

    // Private ID document access strictly scoped to instructor.manage with no-store header
    $this->actingAs($applicant)->get(route('instructor-applications.document', $application))->assertForbidden();
    $this->actingAs($finance)->get(route('instructor-applications.document', $application))->assertForbidden();
    $this->actingAs($support)->get(route('instructor-applications.document', $application))->assertForbidden();

    $docResponse = $this->actingAs($admin)->get(route('instructor-applications.document', $application));
    $docResponse->assertOk();
    expect(strtolower((string) $docResponse->headers->get('Cache-Control')))->toContain('private')->toContain('no-store');

    // Admin approves via Filament table ApproveRejectAction -> grants role + draft profile (not public)
    Filament::setCurrentPanel('admin');
    $this->actingAs($admin);
    Livewire::test(ListInstructorApplications::class)
        ->callTableAction('approve', $application, data: ['note' => 'Verified creative portfolio.']);

    $application->refresh();
    $applicant->refresh();
    expect($application->status)->toBe(ApplicationStatus::Approved)
        ->and($application->pending_user_id)->toBeNull()
        ->and($applicant->hasRole('student'))->toBeTrue()
        ->and($applicant->hasRole('instructor_independent'))->toBeTrue()
        ->and(InstructorProfile::query()->where('user_id', $applicant->id)->value('status'))->toBe(InstructorProfile::DRAFT)
        ->and(app(PublicInstructors::class)->query()->pluck('user_id')->all())->not->toContain($applicant->id)
        ->and(Activity::query()->where('event', 'user.roles_changed')->count())->toBe(1)
        ->and(Activity::query()->where('event', 'instructor_profile.saved')->count())->toBe(1);

    Event::assertDispatched(InstructorApplicationReviewed::class, fn ($e): bool => $e->application->id === $application->id);

    // Repeating approve is idempotent; rejecting an approved one is refused
    app(ApproveInstructorApplication::class)($admin, $application);
    expect(InstructorProfile::query()->where('user_id', $applicant->id)->count())->toBe(1)
        ->and(fn () => app(RejectInstructorApplication::class)($admin, $application, 'Cannot reject after approval.'))->toThrow(ValidationException::class);

    // Rejection flow: short reason refused, valid reason releases pending slot so applicant can re-apply
    $second = User::factory()->create(['status' => 'active', 'email_verified_at' => now()])->assignRole('student');
    $rejectedApp = $submit($second, ioValidApplicationPayload(), UploadedFile::fake()->image('id2.png', 20, 20), 'en');
    expect(fn () => app(RejectInstructorApplication::class)($admin, $rejectedApp, 'Too short'))->toThrow(ValidationException::class);

    app(RejectInstructorApplication::class)($admin, $rejectedApp, 'Portfolio examples are unrelated to children art.');
    $rejectedApp->refresh();
    $second->refresh();
    expect($rejectedApp->status)->toBe(ApplicationStatus::Rejected)
        ->and($rejectedApp->pending_user_id)->toBeNull()
        ->and($second->hasRole('instructor_independent'))->toBeFalse()
        ->and(fn () => app(ApproveInstructorApplication::class)($admin, $rejectedApp))->toThrow(ValidationException::class);

    $reapplied = $submit($second, ioValidApplicationPayload(), UploadedFile::fake()->image('id3.png', 20, 20), 'en');
    expect($reapplied->status)->toBe(ApplicationStatus::Pending);
})->group('T-P9-01', 'YG-GAP-INS-001');

it('enforces versioned instructor agreement acceptance before independent course creation', function (): void {
    Event::fake([InstructorAgreementAccepted::class]);

    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $independent = User::factory()->create(['status' => 'active'])->assignRole('instructor_independent');
    $academy = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $student = User::factory()->create(['status' => 'active'])->assignRole('student');

    // Draft-only agreement blocks nobody
    $saveAgreement = app(SaveInstructorAgreement::class);
    expect(fn () => $saveAgreement($admin, null, ['version' => 'Bad Version!', 'title' => ['ar' => 'ع', 'en' => 'E'], 'body' => ['ar' => 'نص', 'en' => 'Body']]))
        ->toThrow(ValidationException::class)
        ->and(fn () => $saveAgreement($admin, null, ['version' => 'v1-2026', 'title' => ['ar' => '', 'en' => 'Terms'], 'body' => ['ar' => 'نص', 'en' => 'Body']]))
        ->toThrow(ValidationException::class);

    $v1 = $saveAgreement($admin, null, [
        'version' => 'v1-2026',
        'title' => ['ar' => 'اتفاقية المدرب v1', 'en' => 'Instructor Agreement v1'],
        'body' => ['ar' => 'بنود التعاون والتدريس.', 'en' => 'Teaching collaboration terms v1.'],
    ]);
    expect(Gate::forUser($independent)->allows('create', Course::class))->toBeTrue();

    // Publish makes it immutable and blocks independent instructor course creation (A2)
    $publishAgreement = app(PublishInstructorAgreement::class);
    $publishAgreement($admin, $v1);
    $publishAgreement($admin, $v1); // idempotent
    expect(Activity::query()->where('event', 'instructor_agreement.published')->count())->toBe(1)
        ->and(fn () => $saveAgreement($admin, $v1->fresh(), ['title' => ['ar' => 'تعديل', 'en' => 'Edit'], 'body' => ['ar' => 'نص', 'en' => 'Body']]))
        ->toThrow(ValidationException::class)
        ->and(InstructorAgreements::requiresAcceptance($independent))->toBeTrue()
        ->and(Gate::forUser($independent)->allows('create', Course::class))->toBeFalse()
        ->and(Gate::forUser($academy)->allows('create', Course::class))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('create', Course::class))->toBeTrue();

    // Non-independent instructors cannot open agreement page; invalid IP sanitizes to null; accept unblocks A2
    Filament::setCurrentPanel('instructor');
    $this->actingAs($student);
    Livewire::test(InstructorAgreementPage::class)->assertForbidden();
    $this->actingAs($academy);
    Livewire::test(InstructorAgreementPage::class)->assertForbidden();

    $draftOnly = $saveAgreement($admin, null, [
        'version' => 'v1-draft-only',
        'title' => ['ar' => 'مسودة', 'en' => 'Draft'],
        'body' => ['ar' => 'محتوى المسودة.', 'en' => 'Draft wording.'],
    ]);
    $accept = app(AcceptInstructorAgreement::class);
    expect(fn () => $accept($student, $v1, '127.0.0.1'))->toThrow(AuthorizationException::class)
        ->and(fn () => $accept($independent, $draftOnly, '127.0.0.1'))->toThrow(ValidationException::class);

    $this->actingAs($independent);
    Livewire::test(InstructorAgreementPage::class)->assertSee('v1-2026');

    $acceptance = $accept($independent, $v1->fresh(), 'not-an-ip-address');
    $repeatAcceptance = $accept($independent, $v1->fresh(), '10.0.0.1');
    expect($acceptance->id)->toBe($repeatAcceptance->id)
        ->and($acceptance->version)->toBe('v1-2026')
        ->and($acceptance->ip)->toBeNull()
        ->and(InstructorAgreementAcceptance::query()->count())->toBe(1)
        ->and(Activity::query()->where('event', 'instructor_agreement.accepted')->count())->toBe(1)
        ->and(Gate::forUser($independent)->allows('create', Course::class))->toBeTrue();
    Event::assertDispatchedTimes(InstructorAgreementAccepted::class, 1);

    // Publishing v2 re-blocks independent instructor until v2 is accepted via page action
    $v2 = $saveAgreement($admin, null, [
        'version' => 'v2-2026',
        'title' => ['ar' => 'اتفاقية المدرب v2', 'en' => 'Instructor Agreement v2'],
        'body' => ['ar' => 'بنود محدثة.', 'en' => 'Updated collaboration terms v2.'],
    ]);
    $publishAgreement($admin, $v2);
    expect(Gate::forUser($independent)->allows('create', Course::class))->toBeFalse();

    $this->actingAs($independent);
    Livewire::test(InstructorAgreementPage::class)->callAction('accept');
    expect(Gate::forUser($independent)->allows('create', Course::class))->toBeTrue();
})->group('T-P9-01', 'A2', 'YG-GAP-INS-002');

it('encrypts payout profiles at rest, hides them from non-finance, and audits reveals safely', function (): void {
    $finance = User::factory()->create(['status' => 'active'])->assignRole('finance');
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $support = User::factory()->create(['status' => 'active'])->assignRole('support');
    $student = User::factory()->create(['status' => 'active'])->assignRole('student');
    $instructor = User::factory()->create(['status' => 'active', 'name' => 'Karim Artist'])->assignRole('instructor_independent');

    expect(PayoutReadiness::isReady($instructor))->toBeFalse();

    $savePayout = app(SavePayoutProfile::class);
    expect(fn () => $savePayout($student, ['method' => PayoutProfile::WALLET, 'holder_name' => 'Student', 'wallet_number' => '01012345678']))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => $savePayout($instructor, ['method' => PayoutProfile::BANK, 'holder_name' => 'Karim Artist', 'bank_name' => 'CIB', 'account_number' => '123']))
        ->toThrow(ValidationException::class)
        ->and(fn () => $savePayout($instructor, ['method' => PayoutProfile::WALLET, 'holder_name' => 'Karim Artist', 'wallet_number' => '123']))
        ->toThrow(ValidationException::class);

    $bankProfile = $savePayout($instructor, [
        'method' => PayoutProfile::BANK,
        'holder_name' => 'Karim Secret Holder',
        'bank_name' => 'NBE Secret Bank',
        'account_number' => 'eg12-9876-5432-4321',
    ]);

    expect(PayoutReadiness::isReady($instructor))->toBeTrue()
        ->and($bankProfile->details['account_number'])->toBe('EG12987654324321');

    // A1: Encrypted DB payload + serialization secrecy
    $rawCipher = (string) DB::table('instructor_payout_profiles')->where('id', $bankProfile->id)->value('details');
    expect($rawCipher)->not->toBe('')
        ->not->toContain('Karim Secret Holder')
        ->not->toContain('NBE Secret Bank')
        ->not->toContain('4321')
        ->not->toContain('EG12');

    $serialized = json_encode($bankProfile->toArray()).$bankProfile->toJson();
    expect($serialized)->not->toContain('Karim Secret Holder')
        ->not->toContain('4321')
        ->not->toContain('EG12987654324321')
        ->and($bankProfile->toArray())->not->toHaveKey('details');

    // Wallet update replaces prior details
    $walletProfile = $savePayout($instructor, [
        'method' => PayoutProfile::WALLET,
        'holder_name' => 'Karim Wallet Holder',
        'wallet_number' => '+201099887766',
    ]);
    expect(PayoutMask::identifier($walletProfile))->toEndWith('7766')
        ->and(PayoutMask::identifier($walletProfile))->not->toContain('0109988');

    // Finance-only permissions & UI isolation
    expect(Gate::forUser($finance)->allows('payout_profile.view'))->toBeTrue()
        ->and(Gate::forUser($finance)->allows('payout_profile.reveal'))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('payout_profile.view'))->toBeFalse()
        ->and(Gate::forUser($support)->allows('payout_profile.view'))->toBeFalse()
        ->and(Gate::forUser($instructor)->allows('payout_profile.view'))->toBeFalse();

    Filament::setCurrentPanel('finance');
    $this->actingAs($admin);
    Livewire::test(ListPayoutProfiles::class)->assertForbidden();

    $this->actingAs($finance);
    $listHtml = Livewire::test(ListPayoutProfiles::class)
        ->assertOk()
        ->assertSee(PayoutMask::identifier($walletProfile))
        ->html();
    expect($listHtml)->not->toContain('01099887766')
        ->not->toContain('Karim Wallet Holder');

    // Write-only MyPayoutProfile never ships stored holder/wallet values
    Filament::setCurrentPanel('instructor');
    $this->actingAs($instructor);
    $myPayoutHtml = Livewire::test(MyPayoutProfile::class)
        ->assertOk()
        ->assertSee(PayoutMask::identifier($walletProfile))
        ->html();
    expect($myPayoutHtml)->not->toContain('01099887766')
        ->not->toContain('Karim Wallet Holder');

    // Reveal authorization, fail-closed audit, and zero PII in activity_log
    $reveal = app(RevealPayoutProfile::class);
    expect(fn () => $reveal($admin, $walletProfile))->toThrow(AuthorizationException::class)
        ->and(fn () => $reveal($instructor, $walletProfile))->toThrow(AuthorizationException::class);

    $revealed = $reveal($finance, $walletProfile);
    expect($revealed['wallet_number'])->toBe('+201099887766')
        ->and(Activity::query()->where('event', 'payout_profile.revealed')->count())->toBe(1);

    activity()->disableLogging();
    try {
        expect(fn () => $reveal($finance, $walletProfile))->toThrow(RuntimeException::class);
    } finally {
        activity()->enableLogging();
    }

    $allAuditProps = Activity::query()->whereIn('event', ['payout_profile.created', 'payout_profile.updated', 'payout_profile.revealed'])
        ->pluck('properties')->map(fn ($p): string => (string) json_encode($p))->implode(' ');
    expect($allAuditProps)->not->toContain('Karim')
        ->not->toContain('Secret Bank')
        ->not->toContain('4321')
        ->not->toContain('01099887766');
})->group('T-P9-01', 'A1', 'YG-GAP-INS-003');
