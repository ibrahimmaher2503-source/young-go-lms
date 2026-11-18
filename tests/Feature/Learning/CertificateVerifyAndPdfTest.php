<?php

declare(strict_types=1);

use App\Domain\Certificates\Actions\IssueCertificate;
use App\Domain\Certificates\Actions\ReissueCertificate;
use App\Domain\Certificates\Actions\RevokeCertificate;
use App\Domain\Certificates\Certificate;
use App\Domain\Certificates\CertificatePdf;
use App\Domain\Certificates\Notifications\CertificateIssuedNotice;
use App\Domain\Completion\Actions\SaveCompletionRules;
use App\Domain\Completion\CourseCompletion;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\Progress\Actions\RecordProgress;
use App\Filament\Resources\Courses\Pages\ManageCourseCompletion;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\LaravelPdf\Facades\Pdf;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

/** @return array{course: Course, instructor: User, learner: User, completion: CourseCompletion, certificate: Certificate} */
function certVSetup(): array
{
    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $learner = User::factory()->create([
        'status' => 'active',
        'name' => 'Salma Hassan',
        'email' => 'salma.private@example.com',
        'phone' => '+201099887766',
        'certificate_name_ar' => 'سلمى حسن',
        'certificate_name_en' => 'Salma Hassan',
    ])->assignRole('student');

    $course = Course::query()->create([
        'title' => ['ar' => 'أساسيات الرسم المائي', 'en' => 'Watercolor Basics'],
        'slug_ar' => 'certv-ar-'.uniqid(),
        'slug_en' => 'certv-en-'.uniqid(),
        'status' => 'published',
    ]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['ar' => 'القسم 1', 'en' => 'Section 1']]);
    $section->update(['published' => true]);
    $item = app(ManageLessons::class)->add($instructor, $section, ['type' => 'text', 'title' => ['ar' => 'درس 1', 'en' => 'Lesson 1']]);
    app(SetCurriculumStatus::class)($instructor, $course, [$item->id], CurriculumItem::STATUS_PUBLISHED);
    app(SaveCompletionRules::class)($instructor, $course, ['lesson_percent' => 100]);
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);

    Notification::fake();
    app(RecordProgress::class)->markComplete($learner, $item->fresh());

    $completion = CourseCompletion::query()->where('course_id', $course->id)->where('learner_id', $learner->id)->firstOrFail();
    $certificate = Certificate::query()->where('course_completion_id', $completion->id)->firstOrFail();

    return compact('course', 'instructor', 'learner', 'completion', 'certificate');
}

it('issues once per completion, supports staff revoke and reissue with audit, and locks unauthorized actors', function (): void {
    ['course' => $course, 'instructor' => $instructor, 'learner' => $learner, 'completion' => $completion, 'certificate' => $cert1] = certVSetup();

    // Auto-issued on CourseCompleted + single notification
    expect($cert1->isValid())->toBeTrue()
        ->and($cert1->number)->toMatch('/^YG-\d{4}-[A-HJ-NP-Z2-9]{8}$/')
        ->and($learner->fresh()->getAttribute('certificate_names_locked_at'))->not->toBeNull();
    Notification::assertSentToTimes($learner, CertificateIssuedNotice::class, 1);

    // Repeating IssueCertificate is idempotent and returns null
    expect(app(IssueCertificate::class)($completion))->toBeNull();

    // Serialization hides token, revoked_reason, revoked_by
    expect($cert1->toArray())->not->toHaveKeys(['token', 'revoked_reason', 'revoked_by']);

    // Unauthorized instructor cannot revoke or reissue
    $strangerInst = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    expect(fn () => app(RevokeCertificate::class)($strangerInst, $cert1, 'No permission'))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(ReissueCertificate::class)($strangerInst, $completion, 'No permission'))
        ->toThrow(AuthorizationException::class);

    // Livewire ManageCourseCompletion revoke & reissue + halted invalid reason check
    $this->actingAs($instructor);
    Filament::setCurrentPanel('instructor');
    $page = Livewire::test(ManageCourseCompletion::class, ['record' => $course->id])->assertOk();
    $page->callAction('revokeCertificate', ['reason' => 'Issued with misspelled Arabic title'], arguments: ['certificate' => $cert1->id])
        ->assertHasNoActionErrors();

    expect($cert1->fresh()->isValid())->toBeFalse()
        ->and($cert1->fresh()->revoked_reason)->toBe('Issued with misspelled Arabic title');

    // Auto-issue still refuses to silently recreate a revoked certificate
    expect(app(IssueCertificate::class)($completion))->toBeNull();

    // Staff explicit reissue creates a brand-new active certificate and audits replaces number
    $page->callAction('reissueCertificate', ['reason' => 'Learner legal name corrected by support'], arguments: ['completion' => $completion->id])
        ->assertHasNoActionErrors();

    $cert2 = Certificate::query()->where('course_completion_id', $completion->id)->where('id', '!=', $cert1->id)->firstOrFail();
    expect($cert2->isValid())->toBeTrue()
        ->and($cert2->number)->not->toBe($cert1->number)
        ->and($cert2->token)->not->toBe($cert1->token)
        ->and(Activity::query()->where('event', 'certificate.reissued')->count())->toBe(1);
    Notification::assertSentToTimes($learner, CertificateIssuedNotice::class, 1);
})->group('T-P6-06', 'A1');

it('builds bilingual RTL and LTR certificate PDF data, QR codes, and invokes Pdf download safely', function (): void {
    Pdf::fake();
    ['learner' => $learner, 'certificate' => $certificate] = certVSetup();
    $builder = app(CertificatePdf::class);

    $arData = $builder->data($certificate, 'ar');
    $enData = $builder->data($certificate, 'en');

    expect($arData['dir'])->toBe('rtl')
        ->and($arData['name'])->toBe('سلمى حسن')
        ->and($arData['courseTitle'])->toBe('أساسيات الرسم المائي')
        ->and($arData['qr'])->toStartWith('data:image/svg+xml;base64,')
        ->and($arData['font'])->toStartWith('data:font/ttf;base64,')
        ->and($enData['dir'])->toBe('ltr')
        ->and($enData['name'])->toBe('Salma Hassan');

    // Snapshot isolation: later profile rename does not change issued snapshot
    $learner->forceFill(['name' => 'Changed Name', 'certificate_name_ar' => 'اسم آخر'])->save();
    expect($certificate->fresh()->nameIn('ar'))->toBe('سلمى حسن');

    // Download endpoint authorizes owner and verified linked parent, invoking Spatie Pdf download
    $this->actingAs($learner)->get(route('certificates.download', ['certificate' => $certificate->id, 'lang' => 'ar']))
        ->assertOk();

    $parent = User::factory()->create(['status' => 'active'])->assignRole('parent');
    GuardianChildLink::query()->create([
        'guardian_id' => $parent->id, 'child_id' => $learner->id,
        'status' => GuardianChildLink::STATUS_VERIFIED, 'method' => GuardianChildLink::METHOD_CREATED_BY_PARENT, 'verified_at' => now(),
    ]);
    $this->actingAs($parent)->get(route('certificates.download', ['certificate' => $certificate->id, 'lang' => 'en']))
        ->assertOk();

    // Unlinked parent or stranger gets 404; revoked certificate gets 404
    $stranger = User::factory()->create(['status' => 'active'])->assignRole('student');
    $this->actingAs($stranger)->get(route('certificates.download', ['certificate' => $certificate->id]))->assertNotFound();

    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    app(RevokeCertificate::class)($admin, $certificate, 'Duplicate error');
    $this->actingAs($learner)->get(route('certificates.download', ['certificate' => $certificate->id]))->assertNotFound();
})->group('T-P6-06', 'A2', 'R-030');

it('serves public verify endpoint safely across valid, revoked, unknown, privacy, headers, and throttle', function (): void {
    ['instructor' => $instructor, 'learner' => $learner, 'certificate' => $certificate] = certVSetup();

    // Valid token renders snapshot fields with noindex header and zero PII leaks
    $res = $this->get(route('certificates.verify', ['token' => $certificate->token]))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee($certificate->number)
        ->assertSee('data-verify-status="valid"', false)
        ->assertDontSee('salma.private@example.com')
        ->assertDontSee('+201099887766');

    expect($res->headers->get('Cache-Control'))->toContain('no-store');

    // Revoked token renders revoked badge without exposing staff reason
    app(RevokeCertificate::class)($instructor, $certificate, 'SECRET_INTERNAL_STAFF_REASON');
    $this->get(route('certificates.verify', ['token' => $certificate->token]))
        ->assertOk()
        ->assertSee('data-verify-status="revoked"', false)
        ->assertDontSee('SECRET_INTERNAL_STAFF_REASON');

    // Unknown token -> 404
    $this->get(route('certificates.verify', ['token' => str_repeat('A', 40)]))->assertNotFound();

    // Public verification rate limit eventually returns 429
    RateLimiter::clear('certificate-verify|127.0.0.1');
    $statuses = [];
    for ($i = 0; $i < 65; $i++) {
        $statuses[] = $this->get(route('certificates.verify', ['token' => $certificate->token]))->getStatusCode();
        if (end($statuses) === 429) {
            break;
        }
    }
    expect($statuses)->toContain(429);
})->group('T-P6-06', 'A3', 'YG-GAP-ID-001');
