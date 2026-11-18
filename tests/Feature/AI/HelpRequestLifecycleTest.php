<?php

// T-P7-04 | AI spec §12 §16, PRD §15.5 §16, D-019..D-021 | A1 A2 A3 A4

declare(strict_types=1);

use App\Domain\AI\Help\Actions\ChangeHelpRequestStatus;
use App\Domain\AI\Help\Actions\EscalateOverdueHelpRequests;
use App\Domain\AI\Help\Actions\ReplyAsStudent;
use App\Domain\AI\Help\Actions\ReplyToHelpRequest;
use App\Domain\AI\Help\HelpRefusedException;
use App\Domain\AI\Help\HelpRequestNotices;
use App\Domain\AI\Help\HelpRequestStatus;
use App\Domain\AI\Help\Notifications\HelpRequestNotice;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\Notifications\GuardianCopyNotification;
use App\Domain\Notifications\NotificationDispatch;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Course;
use App\Models\HelpRequest;
use App\Models\HelpRequestEvent;
use App\Models\HelpRequestMessage;
use App\Models\Lesson;
use App\Models\LessonCurriculumDocument;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
});

/**
 * @return array{0: Course, 1: Lesson, 2: User, 3: User}
 */
function hrlCourse(string $title = 'Lifecycle Course'): array
{
    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $course = Course::query()->create([
        'title' => ['en' => $title, 'ar' => $title.' ar'],
        'slug_ar' => 'lc-ar-'.uniqid(),
        'slug_en' => 'lc-en-'.uniqid(),
        'status' => 'published',
        'ai_enabled' => true,
    ]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);

    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'Section']]);
    $item = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Lesson Title'], 'type' => 'video']);
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

    $student = User::factory()->create(['status' => 'active', 'email' => 'student@example.test'])->assignRole('student');

    return [$course, $lesson, $instructor, $student];
}

function hrlCreateRequest(
    Course $course,
    Lesson $lesson,
    User $student,
    ?User $assignedInstructor = null,
    HelpRequestStatus $status = HelpRequestStatus::New,
    ?DateTimeInterface $dueAt = null,
): HelpRequest {
    $conv = AiConversation::query()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'lesson_id' => $lesson->id,
        'document_version' => 1,
        'locale' => 'en',
        'style_tier' => 'standard',
        'video_position' => 45,
        'status' => 'active',
    ]);

    $msg = AiMessage::query()->create([
        'conversation_id' => $conv->id,
        'role' => AiMessage::ROLE_ASSISTANT,
        'content' => 'The assistant answer to be clarified',
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
        'video_position' => 45,
        'document_version' => 1,
        'ai_status' => 'answered',
        'source_pages' => [1],
        'question' => 'My question about perspective',
        'ai_answer' => 'The assistant answer to be clarified',
        'due_at' => $dueAt ?? now()->addHours(24),
    ]);
}

it('handles staff reply: transitions state, stops SLA, sets first_replied_at once, and notifies learner with safe text', function (): void {
    [$course, $lesson, $instructor, $student] = hrlCourse();
    $request = hrlCreateRequest($course, $lesson, $student, $instructor);

    $replyAction = app(ReplyToHelpRequest::class);

    // 1. First reply without waitForStudent
    $firstTime = now()->subMinutes(10);
    $this->travelTo($firstTime);
    $reply1 = $replyAction($instructor, $request, 'Here is how perspective works in drawing.', false);

    expect($request->refresh()->status)->toBe(HelpRequestStatus::InstructorReplied);
    expect($request->due_at)->toBeNull();
    expect($request->first_replied_at)->not->toBeNull();
    $recordedFirstRepliedAt = $request->first_replied_at;

    // Check message row
    expect($reply1->role)->toBe(HelpRequestMessage::ROLE_INSTRUCTOR);
    expect($reply1->author_id)->toBe($instructor->id);
    expect($reply1->body)->toBe('Here is how perspective works in drawing.');

    // Check event row
    $event1 = HelpRequestEvent::where('help_request_id', $request->id)->where('event', 'replied')->first();
    expect($event1)->not->toBeNull();
    expect($event1?->actor_id)->toBe($instructor->id);
    expect($event1?->from_status)->toBe('new');
    expect($event1?->to_status)->toBe('instructor_replied');

    // Check audit row
    $audit1 = Activity::where('log_name', 'audit')->where('event', 'help_request.replied')->first();
    expect($audit1)->not->toBeNull();
    expect((int) $audit1?->causer_id)->toBe($instructor->id);
    expect($audit1?->properties['context']['message_id'])->toBe($reply1->id);
    expect(json_encode($audit1?->properties))->not->toContain('Here is how perspective works');

    // Check learner notification in DB
    $dbNotification = $student->notifications()->first();
    expect($dbNotification)->not->toBeNull();
    $notifData = $dbNotification?->data;
    // Notice contains only the lesson title, never the reply, question or answer text
    expect($notifData['message'])->toContain((string) $lesson->title);
    expect($notifData['message'])->not->toContain('Here is how perspective works');
    expect($notifData['message'])->not->toContain('My question about perspective');
    expect($notifData['message'])->not->toContain('The assistant answer to be clarified');

    // Check mail message lines
    $mailMsg = (new HelpRequestNotice(HelpRequestNotice::REPLY, $request->id, (string) $lesson->title, 'dedupe'))->toMail($student);
    $mailBody = implode(' ', $mailMsg->introLines);
    expect($mailBody)->toContain((string) $lesson->title);
    expect($mailBody)->not->toContain('Here is how perspective works');
    expect($mailBody)->not->toContain('My question about perspective');

    // 2. Second reply with waitForStudent
    $this->travelTo(now()->addMinutes(5));
    $reply2 = $replyAction($instructor, $request, 'Can you send a photo of your canvas?', true);

    expect($request->refresh()->status)->toBe(HelpRequestStatus::WaitingForStudent);
    // first_replied_at is kept unchanged
    expect($request->first_replied_at->toIso8601String())->toBe($recordedFirstRepliedAt?->toIso8601String());

    // Two distinct reply messages dispatched two notices
    expect(NotificationDispatch::where('recipient_id', $student->id)->count())->toBe(2);

    // Retrying notice with same reply message key sends nothing more
    $notices = app(HelpRequestNotices::class);
    $notices->replied($request, $reply2->id);
    expect(NotificationDispatch::where('recipient_id', $student->id)->count())->toBe(2);
})->group('T-P7-04', 'A3');

it('delivers in-app notice to child without email and guardian receives email copy without private text', function (): void {
    NotificationFacade::fake();
    [$course, $lesson, $instructor] = hrlCourse();

    // Child account without email
    $child = User::factory()->create(['status' => 'active', 'email' => null])->assignRole('student');
    $guardian = User::factory()->create([
        'status' => 'active',
        'email' => 'parent@example.test',
        'email_verified_at' => now(),
    ])->assignRole('parent');

    GuardianChildLink::query()->create([
        'guardian_id' => $guardian->id,
        'child_id' => $child->id,
        'status' => GuardianChildLink::STATUS_VERIFIED,
        'method' => GuardianChildLink::METHOD_CREATED_BY_PARENT,
        'verified_at' => now(),
    ]);

    $request = hrlCreateRequest($course, $lesson, $child, $instructor);

    $replyAction = app(ReplyToHelpRequest::class);
    $replyAction($instructor, $request, 'Private explanation for child.');

    // Child gets in-app database notice
    NotificationFacade::assertSentTo($child, HelpRequestNotice::class, function (HelpRequestNotice $n, array $channels): bool {
        expect($channels)->toBe(['database']);

        return true;
    });

    // Guardian gets notification copy with NO question or reply text
    NotificationFacade::assertSentTo($guardian, GuardianCopyNotification::class, function (GuardianCopyNotification $n, array $channels) use ($guardian, $lesson): bool {
        $mail = $n->toMail($guardian);
        $body = implode(' ', $mail->introLines);

        expect($channels)->toBe(['mail']);
        expect($body)->toContain((string) $lesson->title);
        expect($body)->not->toContain('Private explanation for child.');
        expect($body)->not->toContain('My question about perspective');

        return true;
    });
})->group('T-P7-04', 'A3');

it('allows learner reply only when status is instructor_replied or waiting_for_student', function (): void {
    [$course, $lesson, $instructor, $student] = hrlCourse();
    $request = hrlCreateRequest($course, $lesson, $student, $instructor, HelpRequestStatus::New);

    $replyAsStudent = app(ReplyAsStudent::class);

    // In 'new' state: not learner's turn
    expect(fn () => $replyAsStudent($student, $request, 'Can you answer me?'))
        ->toThrow(HelpRefusedException::class, __('help.refused.not_your_turn', [
            'max' => (int) config('ai.help.max_open_per_lesson', 3),
            'chars' => (int) config('ai.help.reply_max_chars', 2000),
        ]));

    // In 'in_progress' state: not learner's turn
    $request->forceFill(['status' => HelpRequestStatus::InProgress])->save();
    expect(fn () => $replyAsStudent($student, $request, 'Can you answer me?'))
        ->toThrow(HelpRefusedException::class);

    // In 'resolved' state: closed
    $request->forceFill(['status' => HelpRequestStatus::Resolved])->save();
    expect(fn () => $replyAsStudent($student, $request, 'Can you answer me?'))
        ->toThrow(HelpRefusedException::class, __('help.refused.closed', [
            'max' => (int) config('ai.help.max_open_per_lesson', 3),
            'chars' => (int) config('ai.help.reply_max_chars', 2000),
        ]));

    // In 'closed' state: closed
    $request->forceFill(['status' => HelpRequestStatus::Closed])->save();
    expect(fn () => $replyAsStudent($student, $request, 'Can you answer me?'))
        ->toThrow(HelpRefusedException::class);

    // Now set status to instructor_replied with overdue_at set
    $request->forceFill([
        'status' => HelpRequestStatus::InstructorReplied,
        'overdue_at' => now()->subHour(),
        'due_at' => null,
    ])->save();

    // Successful learner reply
    $msg = $replyAsStudent($student, $request, 'Thank you! I understood the first point.');

    expect($request->refresh()->status)->toBe(HelpRequestStatus::InProgress);
    expect($request->due_at)->not->toBeNull();
    expect($request->overdue_at)->toBeNull();
    expect($msg->role)->toBe(HelpRequestMessage::ROLE_STUDENT);
    expect($msg->body)->toBe('Thank you! I understood the first point.');

    // Event and audit recorded
    expect(HelpRequestEvent::where('help_request_id', $request->id)->where('event', 'student_replied')->exists())->toBeTrue();
    expect(Activity::where('log_name', 'audit')->where('event', 'help_request.student_replied')->exists())->toBeTrue();

    // Assigned instructor is notified
    expect(NotificationDispatch::where('recipient_id', $instructor->id)->exists())->toBeTrue();

    // Learner reply on someone else's request is 404
    $otherStudent = User::factory()->create(['status' => 'active'])->assignRole('student');
    $request->forceFill(['status' => HelpRequestStatus::WaitingForStudent])->save();
    expect(fn () => $replyAsStudent($otherStudent, $request, 'Sneaky reply'))
        ->toThrow(HelpRefusedException::class);
})->group('T-P7-04', 'A3');

it('refuses empty and over-long learner replies with appropriate translations', function (): void {
    [$course, $lesson, $instructor, $student] = hrlCourse();
    $request = hrlCreateRequest($course, $lesson, $student, $instructor, HelpRequestStatus::WaitingForStudent);

    $replyAsStudent = app(ReplyAsStudent::class);

    // Empty reply
    expect(fn () => $replyAsStudent($student, $request, '   '))
        ->toThrow(HelpRefusedException::class, __('help.refused.body_needed', [
            'max' => (int) config('ai.help.max_open_per_lesson', 3),
            'chars' => (int) config('ai.help.reply_max_chars', 2000),
        ]));

    // Over-long reply (2001 chars)
    $long = str_repeat('b', 2001);
    expect(fn () => $replyAsStudent($student, $request, $long))
        ->toThrow(HelpRefusedException::class, __('help.refused.body_too_long', [
            'max' => (int) config('ai.help.max_open_per_lesson', 3),
            'chars' => (int) config('ai.help.reply_max_chars', 2000),
        ]));
})->group('T-P7-04', 'A3');

it('enforces valid staff status transitions and stops SLA when resolving or closing', function (): void {
    [$course, $lesson, $instructor, $student] = hrlCourse();
    $request = hrlCreateRequest($course, $lesson, $student, $instructor, HelpRequestStatus::New);

    $changeStatus = app(ChangeHelpRequestStatus::class);

    // 1. New -> InProgress allowed
    $changeStatus($instructor, $request, HelpRequestStatus::InProgress);
    expect($request->refresh()->status)->toBe(HelpRequestStatus::InProgress);
    expect(HelpRequestEvent::where('help_request_id', $request->id)->where('event', 'status_changed')->count())->toBe(1);

    // InProgress -> InProgress refused (only from New)
    expect(fn () => $changeStatus($instructor, $request, HelpRequestStatus::InProgress))
        ->toThrow(HelpRefusedException::class, __('help.refused.bad_transition', [
            'max' => (int) config('ai.help.max_open_per_lesson', 3),
            'chars' => (int) config('ai.help.reply_max_chars', 2000),
        ]));

    // 2. Resolve from open status (InProgress)
    $changeStatus($instructor, $request, HelpRequestStatus::Resolved);
    expect($request->refresh()->status)->toBe(HelpRequestStatus::Resolved);
    expect($request->resolved_at)->not->toBeNull();
    expect($request->due_at)->toBeNull();

    // 3. Resolved -> InProgress refused
    expect(fn () => $changeStatus($instructor, $request, HelpRequestStatus::InProgress))
        ->toThrow(HelpRefusedException::class);

    // 4. Close from Resolved
    $changeStatus($instructor, $request, HelpRequestStatus::Closed);
    expect($request->refresh()->status)->toBe(HelpRequestStatus::Closed);
    expect($request->closed_at)->not->toBeNull();
    expect($request->due_at)->toBeNull();

    // 5. Any transition from Closed is refused
    expect(fn () => $changeStatus($instructor, $request, HelpRequestStatus::Resolved))
        ->toThrow(HelpRefusedException::class);

    // 6. Close directly from an open status
    $reqOpen = hrlCreateRequest($course, $lesson, $student, $instructor, HelpRequestStatus::New);
    $changeStatus($instructor, $reqOpen, HelpRequestStatus::Closed);
    expect($reqOpen->refresh()->status)->toBe(HelpRequestStatus::Closed);
    expect($reqOpen->closed_at)->not->toBeNull();
})->group('T-P7-04', 'A3');

it('escalates overdue help requests via help:escalate-overdue command idempotently and handles re-escalation after student reply', function (): void {
    [$course, $lesson, $instructor, $student] = hrlCourse();
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');

    // 1. Request overdue by 25 hours
    $reqOverdue = hrlCreateRequest($course, $lesson, $student, $instructor, HelpRequestStatus::New, now()->subHours(25));

    // 2. Request with due_at in the future (not overdue)
    $reqFuture = hrlCreateRequest($course, $lesson, $student, $instructor, HelpRequestStatus::New, now()->addHours(5));

    // 3. Request already replied (due_at null)
    $reqReplied = hrlCreateRequest($course, $lesson, $student, $instructor, HelpRequestStatus::InstructorReplied, null);

    // 4. Request resolved (not overdue)
    $reqResolved = hrlCreateRequest($course, $lesson, $student, $instructor, HelpRequestStatus::Resolved, now()->subHours(10));

    // Run command
    $escalatedCount = Artisan::call('help:escalate-overdue');
    expect($escalatedCount)->toBe(0); // Exit code 0

    $reqOverdue->refresh();
    expect($reqOverdue->overdue_at)->not->toBeNull();

    // Future, replied, resolved are untouched
    expect($reqFuture->refresh()->overdue_at)->toBeNull();
    expect($reqReplied->refresh()->overdue_at)->toBeNull();
    expect($reqResolved->refresh()->overdue_at)->toBeNull();

    // Overdue event recorded
    $overdueEvent = HelpRequestEvent::where('help_request_id', $reqOverdue->id)->where('event', 'overdue')->first();
    expect($overdueEvent)->not->toBeNull();
    expect($overdueEvent?->actor_id)->toBeNull(); // System actor

    // Overdue audit recorded
    $overdueAudit = Activity::where('log_name', 'audit')->where('event', 'help_request.overdue')->first();
    expect($overdueAudit)->not->toBeNull();

    // Dispatched to both assigned instructor and admin managers
    $dedupeKey = 'help-request-overdue:'.$reqOverdue->id.':'.$reqOverdue->due_at->timestamp;
    expect(NotificationDispatch::where('dedupe_key', $dedupeKey)->where('recipient_id', $instructor->id)->exists())->toBeTrue();
    expect(NotificationDispatch::where('dedupe_key', $dedupeKey)->where('recipient_id', $admin->id)->exists())->toBeTrue();

    // Second run changes and sends nothing
    $secondRun = app(EscalateOverdueHelpRequests::class)();
    expect($secondRun)->toBe(0);

    // Now instructor replies, stopping SLA
    app(ReplyToHelpRequest::class)($instructor, $reqOverdue, 'Instructor reply');
    expect($reqOverdue->refresh()->status)->toBe(HelpRequestStatus::InstructorReplied);

    // Student replies: resets overdue_at to null and restarts SLA clock (due_at = now() + 24h)
    app(ReplyAsStudent::class)($student, $reqOverdue, 'Student followup question');
    $reqOverdue->refresh();
    expect($reqOverdue->status)->toBe(HelpRequestStatus::InProgress);
    expect($reqOverdue->overdue_at)->toBeNull();
    expect($reqOverdue->due_at)->not->toBeNull();

    // Time passes: new due_at is now 2 hours in the past
    $newDueTime = now()->subHours(2);
    $reqOverdue->forceFill(['due_at' => $newDueTime])->save();

    // Escalates again once with new dedupe key
    $thirdRun = app(EscalateOverdueHelpRequests::class)();
    expect($thirdRun)->toBe(1);
    $reqOverdue->refresh();
    expect($reqOverdue->overdue_at)->not->toBeNull();

    $newDedupeKey = 'help-request-overdue:'.$reqOverdue->id.':'.$newDueTime->timestamp;
    expect(NotificationDispatch::where('dedupe_key', $newDedupeKey)->where('recipient_id', $instructor->id)->exists())->toBeTrue();
})->group('T-P7-04', 'A3');
