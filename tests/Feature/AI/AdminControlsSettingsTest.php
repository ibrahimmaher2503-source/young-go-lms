<?php

// T-P7-06 | AI spec §11, §14, §18 | A1 A2 A3

declare(strict_types=1);

use App\Domain\AI\Actions\AskLessonAssistant;
use App\Domain\AI\Admin\Actions\DisableAssistantGlobally;
use App\Domain\AI\Admin\Actions\EnableAssistantGlobally;
use App\Domain\AI\Admin\Actions\PruneAiData;
use App\Domain\AI\Admin\Actions\SaveAssistantProvider;
use App\Domain\AI\Admin\Actions\SaveAssistantQuotas;
use App\Domain\AI\Admin\Actions\SetCourseAssistant;
use App\Domain\AI\Admin\Actions\SetLessonAssistant;
use App\Domain\AI\Assistant\AssistantQuestion;
use App\Domain\AI\AssistantEligibility;
use App\Domain\AI\Help\HelpRequestStatus;
use App\Domain\AI\Queries\AssistantReadiness;
use App\Domain\AI\Quota\QuotaLedger;
use App\Domain\AI\Resilience\ProviderRegistry;
use App\Domain\AI\UnavailableReason;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Settings\AiSettings;
use App\Domain\Settings\PlatformFlag;
use App\Filament\Pages\AssistantAvailability;
use App\Models\AiAnswerCache;
use App\Models\AiConversation;
use App\Models\AiEvalRun;
use App\Models\AiFeedback;
use App\Models\AiMessage;
use App\Models\AiPromptVersion;
use App\Models\AiQuotaCounter;
use App\Models\AiSafeguardingFlag;
use App\Models\AiUsage;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\HelpRequest;
use App\Models\Lesson;
use App\Models\LessonCurriculumDocument;
use App\Models\LessonDocumentPage;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Pennant\Feature;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
    config(['ai.adult_age' => 18]);
});

/**
 * @return array{0: User, 1: Lesson, 2: Course, 3: CurriculumItem, 4: User, 5: LessonCurriculumDocument}
 */
function tp706SettingsReadyLesson(): array
{
    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');

    $course = Course::query()->create([
        'title' => ['en' => 'Watercolor Fundamentals', 'ar' => 'أساسيات الألوان المائية'],
        'slug_ar' => 'wc-ar-'.uniqid(),
        'slug_en' => 'wc-en-'.uniqid(),
        'status' => 'published',
        'access_mode' => 'free',
        'ai_enabled' => true,
    ]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);

    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'Unit 1']]);
    $section->update(['published' => true]);

    $item = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Wet on wet technique'], 'type' => 'video']);
    $item->update(['ai_enabled' => true]);
    app(SetCurriculumStatus::class)($instructor, $course, [$item->id], 'published');

    /** @var Lesson $lesson */
    $lesson = $item->itemable;
    $lesson->forceFill(['type' => 'video'])->save();

    $doc = LessonCurriculumDocument::query()->create([
        'lesson_id' => $lesson->id,
        'version' => 1,
        'path' => 'curriculum/wc.pdf',
        'sha256' => str_repeat('w', 64),
        'size' => 1024,
        'pages' => 2,
        'status' => LessonCurriculumDocument::STATUS_READY,
        'active' => true,
    ]);

    LessonDocumentPage::query()->create([
        'document_id' => $doc->id,
        'page_no' => 1,
        'raw_text' => 'Page 1 raw',
        'approved_text' => 'Wet on wet is a painting technique where wet paint is applied to a wet surface.',
        'ocr_used' => false,
    ]);

    LessonDocumentPage::query()->create([
        'document_id' => $doc->id,
        'page_no' => 2,
        'raw_text' => 'Page 2 raw',
        'approved_text' => 'Blending colors on wet paper creates soft, diffuse gradients.',
        'ocr_used' => false,
    ]);

    $student = User::factory()->create([
        'status' => 'active',
        'birth_year' => 1995,
        'birth_month' => 5,
    ])->assignRole('student');

    Feature::for(null)->activate(PlatformFlag::AiAssistant->value);
    Feature::for(null)->activate(PlatformFlag::AiMinors->value);

    app(GrantEntitlement::class)($student, $course, EntitlementSource::Free);

    return [$student, $lesson->refresh(), $course->refresh(), $item->refresh()->load(['course', 'section']), $instructor, $doc];
}

it('enables and disables assistant globally with audit, approvals, and authorization checks', function (): void {
    $superAdmin = User::factory()->create(['status' => 'active'])->assignRole('super_admin');
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');

    // 1. Initial state: disable it first
    Feature::for(null)->deactivate(PlatformFlag::AiAssistant->value);
    expect(EnableAssistantGlobally::isOn())->toBeFalse();

    // 2. Non-super admin cannot enable
    expect(fn () => app(EnableAssistantGlobally::class)($admin, 'REF-123'))->toThrow(AuthorizationException::class);
    expect(fn () => app(EnableAssistantGlobally::class)($instructor, 'REF-123'))->toThrow(AuthorizationException::class);
    expect(EnableAssistantGlobally::isOn())->toBeFalse();

    // 3. Validation: empty or 256-character approval reference is refused
    expect(fn () => app(EnableAssistantGlobally::class)($superAdmin, ''))->toThrow(ValidationException::class);
    expect(fn () => app(EnableAssistantGlobally::class)($superAdmin, '   '))->toThrow(ValidationException::class);
    expect(fn () => app(EnableAssistantGlobally::class)($superAdmin, str_repeat('a', 256)))->toThrow(ValidationException::class);

    // 4. Super admin enables with valid reference
    $reference = 'BOARD-APPROVAL-2026-NOV-01';
    app(EnableAssistantGlobally::class)($superAdmin, $reference);

    expect(EnableAssistantGlobally::isOn())->toBeTrue()
        ->and(Feature::for(null)->active(PlatformFlag::AiAssistant->value))->toBeTrue();

    // Row in flag_approvals
    $this->assertDatabaseHas('flag_approvals', [
        'flag' => PlatformFlag::AiAssistant->value,
        'approval_ref' => $reference,
        'approved_by' => $superAdmin->id,
    ]);

    // Audit logged
    $this->assertDatabaseHas('activity_log', [
        'event' => 'ai.global_enabled',
        'causer_id' => $superAdmin->id,
    ]);

    // 5. Setup ready lesson and verify eligibility when global switch is disabled
    [$student, $lesson] = tp706SettingsReadyLesson();

    // Super admin disables
    app(DisableAssistantGlobally::class)($superAdmin);

    expect(EnableAssistantGlobally::isOn())->toBeFalse()
        ->and(Feature::for(null)->active(PlatformFlag::AiAssistant->value))->toBeFalse();

    $this->assertDatabaseHas('activity_log', [
        'event' => 'ai.global_disabled',
        'causer_id' => $superAdmin->id,
    ]);

    // Eligibility returns GlobalOff when global switch is off
    $availability = app(AssistantEligibility::class)->check($student, $lesson);
    expect($availability->isAvailable())->toBeFalse()
        ->and($availability->reason)->toBe(UnavailableReason::GlobalOff);
})->group('T-P7-06', 'A1', 'A2');

it('switches assistant on course and lesson levels with proper authorization and audit', function (): void {
    [$student, $lesson, $course, $item, $instructor] = tp706SettingsReadyLesson();
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $otherInstructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $suspendedInstructor = User::factory()->create(['status' => 'suspended'])->assignRole('instructor_academy');

    // View-scope instructor
    $viewInstructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $course->instructors()->attach($viewInstructor->id, ['role' => 'instructor', 'scope' => 'view']);

    // 1. Authorization: unauthorized users cannot toggle course switch
    expect(fn () => app(SetCourseAssistant::class)($student, $course, false))->toThrow(AuthorizationException::class);
    expect(fn () => app(SetCourseAssistant::class)($otherInstructor, $course, false))->toThrow(AuthorizationException::class);
    expect(fn () => app(SetCourseAssistant::class)($viewInstructor, $course, false))->toThrow(AuthorizationException::class);
    expect(fn () => app(SetCourseAssistant::class)($suspendedInstructor, $course, false))->toThrow(AuthorizationException::class);

    // 2. Manage-scope instructor disables course assistant
    app(SetCourseAssistant::class)($instructor, $course, false);
    expect($course->refresh()->ai_enabled)->toBeFalse();

    $this->assertDatabaseHas('activity_log', [
        'event' => 'ai.course_switched',
        'causer_id' => $instructor->id,
    ]);

    // Eligibility returns CourseOff
    expect(app(AssistantEligibility::class)->check($student, $lesson)->reason)->toBe(UnavailableReason::CourseOff);
    // Lesson is still playable
    $item = $item->refresh()->load(['course', 'section']);
    expect(app(EntitlementService::class)->canAccessItem($student, $item)->allowed)->toBeTrue();

    // No write and no audit when unchanged
    $auditCountBefore = DB::table('activity_log')->where('event', 'ai.course_switched')->count();
    app(SetCourseAssistant::class)($instructor, $course, false);
    expect(DB::table('activity_log')->where('event', 'ai.course_switched')->count())->toBe($auditCountBefore);

    // Admin re-enables course assistant
    app(SetCourseAssistant::class)($admin, $course, true);
    expect($course->refresh()->ai_enabled)->toBeTrue();

    // 3. Lesson switch
    expect(fn () => app(SetLessonAssistant::class)($student, $item, false))->toThrow(AuthorizationException::class);
    expect(fn () => app(SetLessonAssistant::class)($otherInstructor, $item, false))->toThrow(AuthorizationException::class);

    // Non-lesson item throws ValidationException
    $nonLessonItem = CurriculumItem::query()->create([
        'course_id' => $course->id,
        'section_id' => $item->section_id,
        'itemable_type' => 'quiz',
        'itemable_id' => 1234,
        'sort' => 2,
    ]);
    expect(fn () => app(SetLessonAssistant::class)($instructor, $nonLessonItem, false))->toThrow(ValidationException::class);

    // Manage-scope instructor disables lesson assistant
    app(SetLessonAssistant::class)($instructor, $item, false);
    expect($item->refresh()->ai_enabled)->toBeFalse();

    $this->assertDatabaseHas('activity_log', [
        'event' => 'ai.lesson_switched',
        'causer_id' => $instructor->id,
    ]);

    // Eligibility returns ItemOff
    $lesson->unsetRelation('item');
    expect(app(AssistantEligibility::class)->check($student, $lesson->refresh())->reason)->toBe(UnavailableReason::ItemOff);
    // Lesson is still playable
    $item = $item->refresh()->load(['course', 'section']);
    expect(app(EntitlementService::class)->canAccessItem($student, $item)->allowed)->toBeTrue();

    // No write and no audit when unchanged
    $auditCountBeforeLesson = DB::table('activity_log')->where('event', 'ai.lesson_switched')->count();
    app(SetLessonAssistant::class)($instructor, $item, false);
    expect(DB::table('activity_log')->where('event', 'ai.lesson_switched')->count())->toBe($auditCountBeforeLesson);

    // 4. AssistantAvailability ignores tampered courseId or itemId of another course
    [$otherStudent, $otherLesson, $otherCourse, $otherItem] = tp706SettingsReadyLesson();
    $this->actingAs($instructor);

    Livewire::test(AssistantAvailability::class)
        ->set('courseId', $otherCourse->id)
        ->call('toggleCourse');
    expect($otherCourse->refresh()->ai_enabled)->toBeTrue(); // unchanged!

    Livewire::test(AssistantAvailability::class)
        ->set('courseId', $course->id)
        ->call('toggleLesson', $otherItem->id);
    expect($otherItem->refresh()->ai_enabled)->toBeTrue(); // unchanged!
})->group('T-P7-06', 'A1', 'A2');

it('validates and saves quotas, updating AiSettings and QuotaLedger', function (): void {
    $superAdmin = User::factory()->create(['status' => 'active'])->assignRole('super_admin');
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');

    // 1. Authorization: admin without ai.configure is denied
    expect(fn () => app(SaveAssistantQuotas::class)($admin, [
        'questions_per_student_day' => 10,
        'questions_per_student_lesson_day' => 5,
        'budget_alert_percent' => 80,
    ]))->toThrow(AuthorizationException::class);

    // 2. Validation rules
    $save = app(SaveAssistantQuotas::class);

    // lesson limit above day limit -> fail
    expect(fn () => $save($superAdmin, [
        'questions_per_student_day' => 5,
        'questions_per_student_lesson_day' => 10,
        'budget_alert_percent' => 80,
    ]))->toThrow(ValidationException::class);

    // alert 0 and 101 -> fail
    expect(fn () => $save($superAdmin, [
        'questions_per_student_day' => 10,
        'questions_per_student_lesson_day' => 5,
        'budget_alert_percent' => 0,
    ]))->toThrow(ValidationException::class);

    expect(fn () => $save($superAdmin, [
        'questions_per_student_day' => 10,
        'questions_per_student_lesson_day' => 5,
        'budget_alert_percent' => 101,
    ]))->toThrow(ValidationException::class);

    // budget without currency -> fail
    expect(fn () => $save($superAdmin, [
        'questions_per_student_day' => 10,
        'questions_per_student_lesson_day' => 5,
        'monthly_budget_minor' => 50000,
        'budget_currency' => null,
        'budget_alert_percent' => 80,
    ]))->toThrow(ValidationException::class);

    // 2-letter currency -> fail
    expect(fn () => $save($superAdmin, [
        'questions_per_student_day' => 10,
        'questions_per_student_lesson_day' => 5,
        'monthly_budget_minor' => 50000,
        'budget_currency' => 'US',
        'budget_alert_percent' => 80,
    ]))->toThrow(ValidationException::class);

    // 3. Valid save
    $validData = [
        'questions_per_student_day' => 20,
        'questions_per_student_lesson_day' => 10,
        'monthly_budget_minor' => 100000,
        'course_monthly_budget_minor' => 25000,
        'budget_currency' => 'usd', // stored uppercase
        'budget_alert_percent' => 85,
    ];

    $save($superAdmin, $validData);

    $settings = app(AiSettings::class);
    expect($settings->questions_per_student_day)->toBe(20)
        ->and($settings->questions_per_student_lesson_day)->toBe(10)
        ->and($settings->monthly_budget_minor)->toBe(100000)
        ->and($settings->course_monthly_budget_minor)->toBe(25000)
        ->and($settings->budget_currency)->toBe('USD')
        ->and($settings->budget_alert_percent)->toBe(85);

    // Audit logged
    $this->assertDatabaseHas('activity_log', [
        'event' => 'ai.quotas_saved',
        'causer_id' => $superAdmin->id,
    ]);

    // 4. QuotaLedger uses updated limits
    [$student, $lesson] = tp706SettingsReadyLesson();
    $ledger = app(QuotaLedger::class);
    $reservation = $ledger->reserve($student, $lesson);
    expect($reservation)->not->toBeNull();
    $reservation->refund();
})->group('T-P7-06', 'A1', 'A2');

it('saves provider settings, updates active prompt version, and tracks active key in ProviderRegistry', function (): void {
    $superAdmin = User::factory()->create(['status' => 'active'])->assignRole('super_admin');
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');

    // 1. Authorization
    expect(fn () => app(SaveAssistantProvider::class)($admin, [
        'provider' => 'fake',
        'prompt_version' => 1,
        'breaker_failures' => 5,
        'breaker_window_seconds' => 120,
        'breaker_cool_off_seconds' => 60,
    ]))->toThrow(AuthorizationException::class);

    $save = app(SaveAssistantProvider::class);

    // 2. Validation: openai refused (not in registry keys)
    expect(fn () => $save($superAdmin, [
        'provider' => 'openai',
        'prompt_version' => 1,
        'breaker_failures' => 5,
        'breaker_window_seconds' => 120,
        'breaker_cool_off_seconds' => 60,
    ]))->toThrow(ValidationException::class);

    // Fallback equal to primary refused
    expect(fn () => $save($superAdmin, [
        'provider' => 'fake',
        'fallback_provider' => 'fake',
        'prompt_version' => 1,
        'breaker_failures' => 5,
        'breaker_window_seconds' => 120,
        'breaker_cool_off_seconds' => 60,
    ]))->toThrow(ValidationException::class);

    // Unknown prompt version refused
    expect(fn () => $save($superAdmin, [
        'provider' => 'fake',
        'prompt_version' => 9999,
        'breaker_failures' => 5,
        'breaker_window_seconds' => 120,
        'breaker_cool_off_seconds' => 60,
    ]))->toThrow(ValidationException::class);

    // Create a second prompt version
    AiPromptVersion::query()->create([
        'version' => 2,
        'template' => "V2 template\n<lesson_source>\n</lesson_source>",
        'style_tiers' => ['adults' => 'Adult style', 'kids' => 'Kid style', 'teens' => 'Teen style'],
        'active' => false,
    ]);

    // 3. Valid save
    $save($superAdmin, [
        'provider' => 'fake',
        'fallback_provider' => 'unconfigured',
        'model' => 'gemini-1.5-pro',
        'prompt_version' => 2,
        'breaker_failures' => 4,
        'breaker_window_seconds' => 180,
        'breaker_cool_off_seconds' => 45,
    ]);

    $settings = app(AiSettings::class);
    expect($settings->provider)->toBe('fake')
        ->and($settings->fallback_provider)->toBe('unconfigured')
        ->and($settings->model)->toBe('gemini-1.5-pro')
        ->and($settings->breaker_failures)->toBe(4)
        ->and($settings->breaker_window_seconds)->toBe(180)
        ->and($settings->breaker_cool_off_seconds)->toBe(45);

    // Version 2 is now the single active prompt version
    expect(AiPromptVersion::current()?->version)->toBe(2)
        ->and(AiPromptVersion::query()->where('active', true)->count())->toBe(1);

    // ProviderRegistry::activeKey() matches
    expect(app(ProviderRegistry::class)->activeKey())->toBe('fake')
        ->and(app(ProviderRegistry::class)->fallbackKey())->toBe('unconfigured');

    // Next answer writes provider to ai_usage
    [$student, $lesson] = tp706SettingsReadyLesson();
    app(AskLessonAssistant::class)($student, $lesson, new AssistantQuestion('What is wet on wet?', 'en', 0));

    $this->assertDatabaseHas('ai_usage', [
        'provider' => 'fake',
    ]);

    // In production environment, registry keys() contains only 'unconfigured'
    app()->detectEnvironment(fn () => 'production');
    expect(app(ProviderRegistry::class)->keys())->toEqual(['unconfigured']);
    app()->detectEnvironment(fn () => 'testing'); // restore
})->group('T-P7-06', 'A1', 'A2');

it('evaluates readiness checklist and counts accurately', function (): void {
    // Reset any existing budget / eval
    $settings = app(AiSettings::class);
    $settings->monthly_budget_minor = null;
    $settings->save();
    AiEvalRun::query()->delete();
    config(['ai.adult_age' => null]);
    Feature::for(null)->deactivate(PlatformFlag::AiAssistant->value);

    $readiness = app(AssistantReadiness::class)();
    $checks = collect($readiness['checks'])->keyBy('key');

    expect($checks['global']['state'])->toBe('todo')
        ->and($checks['provider']['state'])->toBe('ok') // fake in testing
        ->and($checks['prompt']['state'])->toBe('ok')
        ->and($checks['eval']['state'])->toBe('todo')
        ->and($checks['budget']['state'])->toBe('todo')
        ->and($checks['ages']['state'])->toBe('todo')
        ->and($checks['minors']['state'])->toBe('info')
        ->and($checks['retention']['state'])->toBe('ok');

    // Setting monthly budget -> budget becomes ok
    $settings->monthly_budget_minor = 50000;
    $settings->save();
    expect(app(AssistantReadiness::class)()['checks'][4]['state'])->toBe('ok');

    // Passing eval run -> eval becomes ok
    AiEvalRun::query()->create([
        'provider' => 'fake',
        'prompt_version' => 1,
        'result' => 'pass',
        'cases_total' => 50,
        'cases_passed' => 50,
        'cases_failed' => 0,
        'score' => 100,
        'duration_ms' => 1500,
    ]);
    $checksAfterEval = collect(app(AssistantReadiness::class)()['checks'])->keyBy('key');
    expect($checksAfterEval['eval']['state'])->toBe('ok');

    // Failing eval run -> eval becomes todo
    AiEvalRun::query()->create([
        'provider' => 'fake',
        'prompt_version' => 1,
        'result' => 'fail',
        'cases_total' => 50,
        'cases_passed' => 40,
        'cases_failed' => 10,
        'score' => 80,
        'duration_ms' => 1500,
    ]);
    $checksAfterFail = collect(app(AssistantReadiness::class)()['checks'])->keyBy('key');
    expect($checksAfterFail['eval']['state'])->toBe('todo');

    // Lesson counts: recorded video/audio lessons with curriculum items
    $counts = app(AssistantReadiness::class)()['lessons'];
    expect($counts['total'])->toBeGreaterThanOrEqual(0)
        ->and($counts['total'])->toBe(
            $counts['none'] + $counts['processing'] + $counts['awaiting_approval'] + $counts['failed'] + $counts['ready']
        );
})->group('T-P7-06', 'A1', 'A2');

it('prunes AI data according to retention settings via PruneAiData and ai:prune command', function (): void {
    [$student, $lesson, $course] = tp706SettingsReadyLesson();

    $settings = app(AiSettings::class);
    $settings->message_retention_days = 30;
    $settings->usage_retention_months = 6;
    $settings->save();

    // 1. Messages: old vs new
    $convOld = AiConversation::query()->create([
        'student_id' => $student->id, 'course_id' => $course->id, 'lesson_id' => $lesson->id,
        'document_version' => 1, 'locale' => 'en', 'style_tier' => 'adults', 'status' => 'open',
    ]);
    $convOld->forceFill(['updated_at' => now()->subDays(40)])->save();

    $msgOld = AiMessage::query()->create([
        'conversation_id' => $convOld->id, 'role' => AiMessage::ROLE_USER, 'content' => 'Old user message',
    ]);
    $msgOld->forceFill(['created_at' => now()->subDays(40)])->save();

    $msgOldAssistant = AiMessage::query()->create([
        'conversation_id' => $convOld->id, 'role' => AiMessage::ROLE_ASSISTANT, 'content' => 'Old answer', 'status' => 'answered',
    ]);
    $msgOldAssistant->forceFill(['created_at' => now()->subDays(40)])->save();

    // Feedback on old message -> should be deleted with message
    AiFeedback::query()->create([
        'message_id' => $msgOldAssistant->id, 'student_id' => $student->id, 'type' => AiFeedback::UNDERSTOOD,
    ]);

    // Message tied to HelpRequest -> should SURVIVE even if older than retention
    $msgKept = AiMessage::query()->create([
        'conversation_id' => $convOld->id, 'role' => AiMessage::ROLE_ASSISTANT, 'content' => 'Keep me', 'status' => 'answered',
    ]);
    $msgKept->forceFill(['created_at' => now()->subDays(50)])->save();

    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    HelpRequest::query()->create([
        'student_id' => $student->id, 'course_id' => $course->id, 'lesson_id' => $lesson->id,
        'assigned_instructor_id' => $instructor->id, 'conversation_id' => $convOld->id, 'ai_message_id' => $msgKept->id,
        'status' => HelpRequestStatus::New, 'locale' => 'en', 'document_version' => 1, 'ai_status' => 'answered',
        'question' => 'Help needed', 'ai_answer' => 'Keep me',
    ]);

    // Conversation with safeguarding flag -> survives even if empty
    $convFlagged = AiConversation::query()->create([
        'student_id' => $student->id, 'course_id' => $course->id, 'lesson_id' => $lesson->id,
        'document_version' => 1, 'locale' => 'en', 'style_tier' => 'adults', 'status' => 'open',
    ]);
    $convFlagged->forceFill(['updated_at' => now()->subDays(60)])->save();
    AiSafeguardingFlag::query()->create([
        'conversation_id' => $convFlagged->id,
        'student_id' => $student->id,
        'lesson_id' => $lesson->id,
        'category' => 'self_harm',
        'severity' => 'critical',
        'status' => 'open',
    ]);

    // 2. Old usage (> 6 months)
    $usageOld = AiUsage::query()->create([
        'student_hash' => str_repeat('u', 64), 'lesson_id' => $lesson->id, 'provider' => 'fake',
        'input_tokens' => 10, 'cached_tokens' => 0, 'output_tokens' => 10, 'cost_minor' => 10, 'latency_ms' => 100, 'result' => 'answered',
    ]);
    $usageOld->forceFill(['created_at' => now()->subMonths(8)])->save();

    // 3. Expired cache
    AiAnswerCache::query()->create([
        'lesson_id' => $lesson->id,
        'document_version' => 1,
        'question_hash' => str_repeat('a', 64),
        'locale' => 'en',
        'style_tier' => 'adults',
        'answer_json' => ['status' => 'answered'],
        'expires_at' => now()->subHour(),
    ]);

    // 4. Old quota counter (> 7 days)
    AiQuotaCounter::query()->create([
        'student_id' => $student->id, 'period' => now()->subDays(10)->toDateString(), 'scope' => 'student_day', 'count' => 5,
    ]);

    // Execute prune action
    $pruner = app(PruneAiData::class);
    $report = $pruner();

    expect($report['messages'])->toBe(2) // msgOld, msgOldAssistant
        ->and($report['usage'])->toBe(1)
        ->and($report['cache'])->toBe(1)
        ->and($report['counters'])->toBe(1);

    // Kept message still exists
    expect(AiMessage::query()->find($msgKept->id))->not->toBeNull();
    // Feedback was deleted
    expect(AiFeedback::query()->where('message_id', $msgOldAssistant->id)->exists())->toBeFalse();
    // Flagged conversation survived
    expect(AiConversation::query()->find($convFlagged->id))->not->toBeNull();

    // Audit logged
    $this->assertDatabaseHas('activity_log', [
        'event' => 'ai.retention_pruned',
    ]);

    // Second run: deletes nothing (idempotent)
    $secondReport = $pruner();
    expect(array_sum($secondReport))->toBe(0);

    // Test artisan command ai:prune
    $exitCode = Artisan::call('ai:prune');
    expect($exitCode)->toBe(0);
})->group('T-P7-06', 'A1', 'A2');
