<?php

// T-P7-04 | AI spec §12 §16, PRD §15.5 §16, D-019..D-021 | A1 A2 A3 A4

declare(strict_types=1);

use App\Domain\AI\Help\Actions\ChangeHelpRequestStatus;
use App\Domain\AI\Help\Actions\ReplyToHelpRequest;
use App\Domain\AI\Help\HelpRequestStatus;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Filament\Resources\HelpRequests\Pages\ListHelpRequests;
use App\Filament\Resources\HelpRequests\Pages\ViewHelpRequest;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiSafeguardingFlag;
use App\Models\Course;
use App\Models\HelpRequest;
use App\Models\HelpRequestMessage;
use App\Models\Lesson;
use App\Models\LessonCurriculumDocument;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
});

/**
 * @return array{0: Course, 1: Lesson, 2: User, 3: User}
 */
function hrqCourse(string $title = 'Course', ?User $instructor = null): array
{
    $instructor ??= User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $course = Course::query()->create([
        'title' => ['en' => $title, 'ar' => $title.' ar'],
        'slug_ar' => 'c-ar-'.uniqid(),
        'slug_en' => 'c-en-'.uniqid(),
        'status' => 'published',
        'ai_enabled' => true,
    ]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);

    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'Sec']]);
    $item = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Les'], 'type' => 'video']);
    /** @var Lesson $lesson */
    $lesson = $item->itemable;
    $lesson->forceFill(['type' => 'video'])->save();

    LessonCurriculumDocument::query()->create([
        'lesson_id' => $lesson->id,
        'version' => 1,
        'path' => 'curriculum/doc.pdf',
        'sha256' => str_repeat('a', 64),
        'size' => 1024,
        'pages' => 2,
        'status' => LessonCurriculumDocument::STATUS_READY,
        'active' => true,
    ]);

    $student = User::factory()->create(['status' => 'active'])->assignRole('student');

    return [$course, $lesson, $instructor, $student];
}

function hrqCreateRequest(
    Course $course,
    Lesson $lesson,
    User $student,
    ?User $assignedInstructor = null,
    HelpRequestStatus $status = HelpRequestStatus::New,
    ?\DateTimeInterface $dueAt = null,
): HelpRequest {
    $conv = AiConversation::query()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'lesson_id' => $lesson->id,
        'document_version' => 1,
        'locale' => 'en',
        'style_tier' => 'standard',
        'video_position' => 60,
        'status' => 'active',
    ]);

    $msg = AiMessage::query()->create([
        'conversation_id' => $conv->id,
        'role' => AiMessage::ROLE_ASSISTANT,
        'content' => 'Sample AI Answer',
        'status' => 'answered',
        'source_pages' => [1],
        'prompt_version' => 1,
        'model' => 'fake',
    ]);

    return HelpRequest::query()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'lesson_id' => $lesson->id,
        'assigned_instructor_id' => $assignedInstructor?->id,
        'conversation_id' => $conv->id,
        'ai_message_id' => $msg->id,
        'status' => $status,
        'locale' => 'en',
        'video_position' => 60,
        'document_version' => 1,
        'ai_status' => 'answered',
        'source_pages' => [1],
        'question' => 'Sample Question',
        'ai_answer' => 'Sample AI Answer',
        'due_at' => $dueAt ?? now()->addHours(24),
    ]);
}

it('allows assigned instructor to see request in queue and detail page, while other instructors get 404 and exclusion', function (): void {
    [$course1, $lesson1, $instructor1, $student1] = hrqCourse('Course 1');
    [$course2, $lesson2, $instructor2, $student2] = hrqCourse('Course 2');

    $req1 = hrqCreateRequest($course1, $lesson1, $student1, $instructor1);
    $req2 = hrqCreateRequest($course2, $lesson2, $student2, $instructor2);

    // Instructor 1 sees req1, cannot see req2 in table or view page
    $this->actingAs($instructor1);
    $this->get('/instructor/help-requests')->assertOk()
        ->assertSee((string) $course1->title)
        ->assertDontSee((string) $course2->title);

    $this->get('/instructor/help-requests/'.$req1->id)->assertOk();
    $this->get('/instructor/help-requests/'.$req2->id)->assertNotFound();

    // Instructor 2 sees req2, cannot see req1 in table or view page
    $this->actingAs($instructor2);
    $this->get('/instructor/help-requests')->assertOk()
        ->assertSee((string) $course2->title)
        ->assertDontSee((string) $course1->title);

    $this->get('/instructor/help-requests/'.$req2->id)->assertOk();
    $this->get('/instructor/help-requests/'.$req1->id)->assertNotFound();
})->group('T-P7-04', 'A2');

it('hides requests from a second instructor on the same course when assigned elsewhere', function (): void {
    [$course, $lesson, $instructor1, $student] = hrqCourse('Shared Course');
    $instructor2 = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $course->instructors()->attach($instructor2->id, ['role' => 'instructor', 'scope' => 'manage']);

    // Request assigned specifically to instructor 1
    $request = hrqCreateRequest($course, $lesson, $student, $instructor1);

    // Instructor 1 can see
    $this->actingAs($instructor1)->get('/instructor/help-requests/'.$request->id)->assertOk();

    // Instructor 2 (same course, but not assigned) gets 404 and excluded from list
    $this->actingAs($instructor2);
    $this->get('/instructor/help-requests')->assertOk()
        ->assertDontSee($student->name);
    $this->get('/instructor/help-requests/'.$request->id)->assertNotFound();
})->group('T-P7-04', 'A2');

it('allows admin and support users to see all requests across all courses', function (): void {
    [$course1, $lesson1, $instructor1, $student1] = hrqCourse('Course Alpha');
    [$course2, $lesson2, $instructor2, $student2] = hrqCourse('Course Beta');

    $req1 = hrqCreateRequest($course1, $lesson1, $student1, $instructor1);
    $req2 = hrqCreateRequest($course2, $lesson2, $student2, $instructor2);

    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $support = User::factory()->create(['status' => 'active'])->assignRole('support');

    foreach ([$admin, $support] as $staffUser) {
        $this->actingAs($staffUser);
        $this->get('/admin/help-requests')->assertOk()
            ->assertSee((string) $course1->title)
            ->assertSee((string) $course2->title);

        $this->get('/admin/help-requests/'.$req1->id)->assertOk();
        $this->get('/admin/help-requests/'.$req2->id)->assertOk();
    }
})->group('T-P7-04', 'A2');

it('forbids students, parents, content managers, and suspended staff from opening either panel page', function (): void {
    [$course, $lesson, $instructor, $student] = hrqCourse();
    $parent = User::factory()->create(['status' => 'active'])->assignRole('parent');
    $contentManager = User::factory()->create(['status' => 'active'])->assignRole('content_manager');
    $suspendedStaff = User::factory()->create(['status' => 'suspended'])->assignRole('admin');

    foreach ([$student, $parent, $contentManager, $suspendedStaff] as $blockedUser) {
        $this->actingAs($blockedUser);
        // Student/Parent cannot access staff panel (/admin or /instructor) -> 403
        // Content manager has panel.admin.access but neither help_request.manage nor help_request.reply -> 403 on help-requests
        $this->get('/admin/help-requests')->assertForbidden();
        $this->get('/instructor/help-requests')->assertForbidden();
    }
})->group('T-P7-04', 'A2');

it('throws AuthorizationException and writes nothing when non-authorised staff executes domain actions', function (): void {
    [$course, $lesson, $instructor1, $student] = hrqCourse();
    $unrelatedInstructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $suspendedAdmin = User::factory()->create(['status' => 'suspended'])->assignRole('admin');
    $studentUser = User::factory()->create(['status' => 'active'])->assignRole('student');

    $request = hrqCreateRequest($course, $lesson, $student, $instructor1);

    $replyAction = app(ReplyToHelpRequest::class);
    $statusAction = app(ChangeHelpRequestStatus::class);

    // Unrelated instructor calling ReplyToHelpRequest
    expect(fn () => $replyAction($unrelatedInstructor, $request, 'My unauthorized reply'))
        ->toThrow(AuthorizationException::class);

    // Suspended admin calling ChangeHelpRequestStatus
    expect(fn () => $statusAction($suspendedAdmin, $request, HelpRequestStatus::InProgress))
        ->toThrow(AuthorizationException::class);

    // Student calling ReplyToHelpRequest
    expect(fn () => $replyAction($studentUser, $request, 'My unauthorized reply'))
        ->toThrow(AuthorizationException::class);

    // Assert nothing was written
    expect(HelpRequestMessage::count())->toBe(0);
    expect($request->refresh()->status)->toBe(HelpRequestStatus::New);
})->group('T-P7-04', 'A2');

it('displays safeguarding flags only to users with ai.conversation.view permission and hides them from others', function (): void {
    [$course, $lesson, $instructor, $student] = hrqCourse();
    $request = hrqCreateRequest($course, $lesson, $student, $instructor);

    // Seed a safeguarding flag on the conversation
    AiSafeguardingFlag::query()->create([
        'student_id' => $student->id,
        'conversation_id' => $request->conversation_id,
        'category' => 'self_harm',
        'status' => 'open',
    ]);

    $superAdmin = User::factory()->create(['status' => 'active'])->assignRole('super_admin');
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $support = User::factory()->create(['status' => 'active'])->assignRole('support');

    // Super admin has ai.conversation.view -> sees [data-help-safeguarding] and category text
    $this->actingAs($superAdmin);
    $resSuper = $this->get('/admin/help-requests/'.$request->id)->assertOk();
    $resSuper->assertSee('data-help-safeguarding', false)
        ->assertSee(__('help.queue.safeguarding'))
        ->assertSee(__('help.queue.flag_category.self_harm'));

    // Admin, Support, Instructor do NOT have ai.conversation.view -> do not see [data-help-safeguarding] and text absent
    foreach ([$admin, $support] as $nonSuperStaff) {
        $this->actingAs($nonSuperStaff);
        $res = $this->get('/admin/help-requests/'.$request->id)->assertOk();
        $res->assertDontSee('data-help-safeguarding', false)
            ->assertDontSee(__('help.queue.flag_category.self_harm'));
    }

    $this->actingAs($instructor);
    $resInst = $this->get('/instructor/help-requests/'.$request->id)->assertOk();
    $resInst->assertDontSee('data-help-safeguarding', false)
        ->assertDontSee(__('help.queue.flag_category.self_harm'));
})->group('T-P7-04', 'A2');

it('filters help requests by course, status, and overdue via Livewire table', function (): void {
    [$courseA, $lessonA, $instructor, $studentA] = hrqCourse('Alpha Academy', null);
    [$courseB, $lessonB, , $studentB] = hrqCourse('Beta Bootcamp', $instructor);

    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');

    // 1. New request on Course A, not overdue (due in 24h)
    $reqNewA = hrqCreateRequest($courseA, $lessonA, $studentA, $instructor, HelpRequestStatus::New, now()->addHours(24));

    // 2. In progress request on Course A, OVERDUE (due 2h ago)
    $reqOverdueA = hrqCreateRequest($courseA, $lessonA, $studentA, $instructor, HelpRequestStatus::InProgress, now()->subHours(2));

    // 3. Resolved request on Course B, past due date (resolved requests are never overdue)
    $reqResolvedB = hrqCreateRequest($courseB, $lessonB, $studentB, $instructor, HelpRequestStatus::Resolved, now()->subHours(5));

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($admin);

    Livewire::test(ListHelpRequests::class)
        ->assertCanSeeTableRecords([$reqNewA, $reqOverdueA, $reqResolvedB])
        // Filter by course A
        ->filterTable('course', $courseA->id)
        ->assertCanSeeTableRecords([$reqNewA, $reqOverdueA])
        ->assertCanNotSeeTableRecords([$reqResolvedB])
        // Reset course filter
        ->filterTable('course', null)
        // Filter by status 'new'
        ->filterTable('status', 'new')
        ->assertCanSeeTableRecords([$reqNewA])
        ->assertCanNotSeeTableRecords([$reqOverdueA, $reqResolvedB])
        // Reset status filter
        ->filterTable('status', null)
        // Filter by overdue toggle: only new/in_progress with due_at in the past
        ->filterTable('overdue', true)
        ->assertCanSeeTableRecords([$reqOverdueA])
        ->assertCanNotSeeTableRecords([$reqNewA, $reqResolvedB]);
})->group('T-P7-04', 'A2');
