<?php

declare(strict_types=1);

// T-DECISIONS-2 | O-015 | a minor's assistant access waits for a verified guardian (guardians.require_verification_for_ai)

use App\Domain\AI\AssistantEligibility;
use App\Domain\AI\UnavailableReason;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Entitlements\Contracts\LessonAccessChecker;
use App\Domain\Guardians\Actions\ConfirmGuardianOtp;
use App\Domain\Guardians\Actions\SendGuardianOtp;
use App\Domain\Guardians\Notifications\GuardianOtpNotice;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\Settings\PlatformFlag;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonCurriculumDocument;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Pennant\Feature;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    config(['ai.adult_age' => null]);
    $this->withoutVite();
});

/**
 * An AI-enabled course with a ready lesson, and a minor learner linked to a parent with AI consent.
 *
 * @return array{0: User, 1: Lesson, 2: User}
 */
function dgaSetup(): array
{
    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $course = Course::query()->create([
        'title' => ['en' => 'Gate Course', 'ar' => 'دورة البوابة'],
        'slug_ar' => 'dga-ar-'.uniqid(),
        'slug_en' => 'dga-en-'.uniqid(),
        'status' => 'published',
        'ai_enabled' => true,
    ]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);

    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'Section 1']]);
    $item = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Lesson 1'], 'type' => 'video']);
    /** @var Lesson $lesson */
    $lesson = $item->itemable;
    $lesson->forceFill(['type' => 'video'])->save();

    LessonCurriculumDocument::query()->create([
        'lesson_id' => $lesson->id,
        'version' => 1,
        'path' => 'curriculum/dga.pdf',
        'sha256' => str_repeat('c', 64),
        'size' => 1024,
        'pages' => 2,
        'status' => LessonCurriculumDocument::STATUS_READY,
        'active' => true,
    ]);

    $student = User::factory()->create(['status' => 'active', 'email' => null, 'email_verified_at' => null])->assignRole('student');
    $parent = User::factory()->create(['status' => 'active', 'email_verified_at' => now()])->assignRole('parent');
    GuardianChildLink::query()->create([
        'guardian_id' => $parent->id,
        'child_id' => $student->id,
        'status' => GuardianChildLink::STATUS_VERIFIED,
        'method' => GuardianChildLink::METHOD_CREATED_BY_PARENT,
        'verified_at' => now(),
        'ai_consent_granted_at' => now(),
        'ai_consent_version' => 'v1',
    ]);

    Feature::for(null)->activate(PlatformFlag::AiAssistant->value);
    Feature::for(null)->activate(PlatformFlag::AiMinors->value);
    app()->bind(LessonAccessChecker::class, fn () => new class implements LessonAccessChecker
    {
        public function canAccessLessonFiles(User $user, Lesson $lesson): bool
        {
            return true;
        }
    });

    return [$student, $lesson->refresh(), $parent];
}

/** The 6-digit code from the guardian notice (only its hash is stored). */
function dgaSentCode(User $parent): string
{
    $code = null;
    Notification::assertSentTo($parent, GuardianOtpNotice::class, function (GuardianOtpNotice $notice) use (&$code): bool {
        $code = (new ReflectionProperty(GuardianOtpNotice::class, 'code'))->getValue($notice);

        return true;
    });

    return (string) $code;
}

it('refuses a minor the assistant with guardian_unverified until the guardian confirms the code', function (): void {
    Notification::fake();
    config(['guardians.require_verification_for_ai' => true]);
    [$student, $lesson, $parent] = dgaSetup();

    $before = app(AssistantEligibility::class)->check($student, $lesson);
    expect($before->isAvailable())->toBeFalse()
        ->and($before->reason)->toBe(UnavailableReason::GuardianUnverified);

    app(SendGuardianOtp::class)->handle($parent);
    app(ConfirmGuardianOtp::class)->handle($parent, dgaSentCode($parent));

    $after = app(AssistantEligibility::class)->check($student, $lesson);
    expect($after->reason)->not->toBe(UnavailableReason::GuardianUnverified);
})->group('T-DECISIONS-2', 'O-015');

it('does not apply the guardian gate when the config flag is off, so the verification step is skipped', function (): void {
    config(['guardians.require_verification_for_ai' => false]);
    [$student, $lesson] = dgaSetup();

    $availability = app(AssistantEligibility::class)->check($student, $lesson);

    expect($availability->reason)->not->toBe(UnavailableReason::GuardianUnverified);
})->group('T-DECISIONS-2', 'O-015');
