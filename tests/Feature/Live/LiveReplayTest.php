<?php

declare(strict_types=1);

// T-P5-05 | PRD A11, ED-032 | A1 replay is an ordinary draft lesson, A2 only a finished session with no replay yet, A3 staff only

use App\Domain\AI\AssistantEligibility;
use App\Domain\AI\UnavailableReason;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Curriculum\Queries\CurriculumOutline;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Live\Actions\CompleteLiveSession;
use App\Domain\Live\Actions\OpenLiveSession;
use App\Domain\Live\Actions\PublishLiveReplay;
use App\Domain\Live\Actions\ScheduleLiveSession;
use App\Domain\Media\Actions\AttachLessonMedia;
use App\Domain\Media\SignedFileUrl;
use App\Domain\Settings\PlatformFlag;
use App\Filament\Resources\Courses\Pages\ManageCurriculum;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use App\Models\LessonCurriculumDocument;
use App\Models\LiveAttendance;
use App\Models\LiveSession;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Pennant\Feature;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

/** @return array{0: Course, 1: LiveSession, 2: User, 3: CourseSection} */
function repBuild(string $status = LiveSession::COMPLETED, array $title = ['en' => 'Kickoff', 'ar' => 'الانطلاق']): array
{
    $admin = User::factory()->create()->assignRole('admin');
    $course = Course::query()->create(['title' => ['en' => 'Replays', 'ar' => 'الإعادات'], 'slug_ar' => 'rep-'.uniqid(), 'slug_en' => 'rep-'.uniqid(), 'status' => 'published', 'access_mode' => 'paid', 'format' => 'live']);
    $section = app(ManageSections::class)->add($admin, $course, ['title' => ['en' => 'Week 1', 'ar' => 'الأسبوع الأول']]);
    $session = app(ScheduleLiveSession::class)($admin, $section, [
        'title' => $title, 'starts_at' => '2027-03-10 18:00', 'ends_at' => '2027-03-10 19:00',
        'timezone' => 'UTC', 'provider' => 'manual_zoom', 'meeting_url' => 'https://zoom.example.com/j/rep',
    ])->itemable;

    if ($status !== LiveSession::SCHEDULED) {
        app(OpenLiveSession::class)($admin, $session);
        if ($status === LiveSession::COMPLETED) {
            app(CompleteLiveSession::class)($admin, $session);
        }
        $session->refresh();
    }

    return [$course, $session, $admin, $section];
}

/** @return array<string, list<string>> */
function repErrors(callable $callback): array
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

it('creates the replay as a bilingual draft video lesson in the same section and links it', function (): void {
    [$course, $session, $admin, $section] = repBuild();

    $item = app(PublishLiveReplay::class)($admin, $session);

    expect($item->status)->toBe(CurriculumItem::STATUS_DRAFT)
        ->and($item->course_id)->toBe($course->getKey())
        ->and($item->section_id)->toBe($section->getKey())
        ->and($item->itemable_type)->toBe('lesson')
        ->and($item->sort)->toBe(2)
        ->and($item->itemable)->toBeInstanceOf(Lesson::class)
        ->and($item->itemable->type)->toBe('video')
        ->and($item->itemable->getTranslation('title', 'en', false))->toBe(__('live.replay.title', ['title' => 'Kickoff'], 'en'))
        ->and($item->itemable->getTranslation('title', 'ar', false))->toBe(__('live.replay.title', ['title' => 'الانطلاق'], 'ar'))
        ->and($session->refresh()->replay_item_id)->toBe($item->getKey());

    expect(Activity::query()->where('event', 'live_session.replay_created')->sole()->properties['after']['item_id'])->toBe($item->getKey());
});

it('refuses a replay before the session has finished', function (): void {
    [$course, $scheduled, $admin] = repBuild(LiveSession::SCHEDULED);

    expect(repErrors(fn () => app(PublishLiveReplay::class)($admin, $scheduled))['status'][0] ?? null)
        ->toBe(__('live.replay.needs_finished'));

    app(OpenLiveSession::class)($admin, $scheduled->refresh());
    expect(repErrors(fn () => app(PublishLiveReplay::class)($admin, $scheduled->refresh()))['status'][0] ?? null)
        ->toBe(__('live.replay.needs_finished'))
        ->and(CurriculumItem::query()->where('course_id', $course->getKey())->count())->toBe(1);
});

it('refuses a second replay for the same session', function (): void {
    [, $session, $admin] = repBuild();
    $item = app(PublishLiveReplay::class)($admin, $session);

    expect(repErrors(fn () => app(PublishLiveReplay::class)($admin, $session->refresh()))['replay'][0] ?? null)
        ->toBe(__('live.replay.exists'))
        ->and(CurriculumItem::query()->count())->toBe(2)
        ->and($session->refresh()->replay_item_id)->toBe($item->getKey());
});

it('refuses learners and instructors of another course', function (): void {
    [, $session, , $section] = repBuild();
    $learner = User::factory()->create()->assignRole('student');
    $outsider = User::factory()->create()->assignRole('instructor_academy');

    expect(fn () => app(PublishLiveReplay::class)($learner, $session))->toThrow(AuthorizationException::class)
        ->and(fn () => app(PublishLiveReplay::class)($outsider, $session))->toThrow(AuthorizationException::class)
        ->and(CurriculumItem::query()->where('section_id', $section->getKey())->count())->toBe(1);
});

it('builds the replay title only for the locales the session has', function (): void {
    [, $session, $admin] = repBuild(LiveSession::COMPLETED, ['en' => 'Only English']);

    $item = app(PublishLiveReplay::class)($admin, $session);

    expect(array_keys($item->itemable->getTranslations('title')))->toBe(['en'])
        ->and($item->itemable->getTranslation('title', 'en', false))->toBe('Replay: Only English');
});

it('stays a draft and out of the student outline until it is published', function (): void {
    [$course, $session, $admin] = repBuild();
    $liveItem = $session->item()->sole();
    $section = $session->item()->firstOrFail()->section;
    $section->update(['published' => true]);
    app(SetCurriculumStatus::class)($admin, $course, [$liveItem->getKey()], CurriculumItem::STATUS_PUBLISHED);

    $item = app(PublishLiveReplay::class)($admin, $session);

    expect(CurriculumOutline::forManager($course)->flatMap->items)->toHaveCount(2)
        ->and(CurriculumOutline::forStudents($course->refresh())->flatMap->items->pluck('id')->all())->toBe([$liveItem->getKey()])
        ->and($item->status)->toBe(CurriculumItem::STATUS_DRAFT);
});

it('requires replay publication, entitlement, and its own active ready PDF', function (): void {
    [$course, $session, $admin, $section] = repBuild();
    $section->update(['published' => true]);
    $item = app(PublishLiveReplay::class)($admin, $session);
    $lesson = $item->itemable;
    config(['ai.adult_age' => 18]);
    Feature::for(null)->activate(PlatformFlag::AiAssistant->value);
    $learner = User::factory()->create(['birth_year' => 1990, 'birth_month' => 1])->assignRole('student');
    $other = User::factory()->create(['birth_year' => 1990, 'birth_month' => 1])->assignRole('student');
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);
    $checker = app(AssistantEligibility::class);
    expect($checker->check($learner, $lesson->fresh())->reason)->toBe(UnavailableReason::NoEntitlement);

    app(SetCurriculumStatus::class)($admin, $course, [$item->id], CurriculumItem::STATUS_PUBLISHED);

    // Another lesson in the same course has a READY PDF, which must never make the replay AI-eligible
    $siblingItem = app(ManageLessons::class)->add($admin, $section, ['type' => 'video', 'title' => ['en' => 'Sibling']]);
    app(SetCurriculumStatus::class)($admin, $course, [$siblingItem->id], CurriculumItem::STATUS_PUBLISHED);
    LessonCurriculumDocument::query()->create([
        'lesson_id' => $siblingItem->itemable_id, 'version' => 1, 'path' => 'curriculum/sibling.pdf', 'sha256' => str_repeat('b', 64), 'size' => 1,
        'pages' => 1, 'status' => LessonCurriculumDocument::STATUS_READY, 'active' => true,
    ]);

    $doc = LessonCurriculumDocument::query()->create([
        'lesson_id' => $lesson->id, 'version' => 1, 'path' => 'curriculum/replay.pdf', 'sha256' => str_repeat('a', 64), 'size' => 1,
        'pages' => 1, 'status' => LessonCurriculumDocument::STATUS_AWAITING_APPROVAL, 'active' => true,
    ]);
    $files = app(SignedFileUrl::class);
    expect($checker->check($learner, $lesson->fresh())->reason)->toBe(UnavailableReason::NoApprovedDocument)
        ->and($files->for($learner, $doc))->toBeNull();

    $doc->update(['status' => LessonCurriculumDocument::STATUS_READY]);
    expect($checker->check($learner, $lesson->fresh())->isAvailable())->toBeTrue()
        ->and($checker->check($other, $lesson->fresh())->reason)->toBe(UnavailableReason::NoEntitlement)
        ->and($files->for($learner, $doc))->not->toBeNull()
        ->and($files->for($other, $doc))->toBeNull();
})->group('T-P5-05', 'A2');

it('exposes the replay link on the learner live card only after media publish, active entitlement, and drip unlock', function (): void {
    [$course, $session, $admin, $section] = repBuild();
    $section->update(['published' => true]);
    $liveItem = $session->item()->sole();
    app(SetCurriculumStatus::class)($admin, $course, [$liveItem->id], CurriculumItem::STATUS_PUBLISHED);

    $learner = User::factory()->create(['status' => 'active'])->assignRole('student');
    $stranger = User::factory()->create(['status' => 'active'])->assignRole('student');
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);

    $replayItem = app(PublishLiveReplay::class)($admin, $session);

    // Draft replay -> no link on live card, 404 on replay route
    $this->actingAs($learner)
        ->get(route('learn.show', ['course' => $course->id, 'item' => $liveItem->id]))
        ->assertOk()
        ->assertDontSee('data-replay-link', false);
    $this->get(route('learn.show', ['course' => $course->id, 'item' => $replayItem->id]))->assertNotFound();

    // Cannot publish a paid replay with a T3 Open media source unless an exception is granted
    app(AttachLessonMedia::class)($admin, $replayItem->itemable, ['provider' => 'youtube', 'url' => 'https://youtu.be/dQw4w9WgXcQ']);
    expect(repErrors(fn () => app(SetCurriculumStatus::class)($admin, $course, [$replayItem->id], CurriculumItem::STATUS_PUBLISHED)))
        ->toHaveKey('items');

    Storage::fake('local');
    Storage::disk('local')->put('media/replay.mp4', 'video');
    app(AttachLessonMedia::class)($admin, $replayItem->itemable->refresh(), ['provider' => 'signed_storage', 'path' => 'media/replay.mp4']);
    app(SetCurriculumStatus::class)($admin, $course, [$replayItem->id], CurriculumItem::STATUS_PUBLISHED);

    // Future drip date blocks replay link and direct replay access
    $replayItem->update(['unlock_rule' => 'date', 'unlock_at' => now()->addDay()]);
    app(EntitlementService::class)->flush();
    $this->actingAs($learner)
        ->get(route('learn.show', ['course' => $course->id, 'item' => $liveItem->id]))
        ->assertOk()
        ->assertDontSee('data-replay-link', false);

    // Unlocked -> entitled learner sees replay link and can open replay; stranger gets 404
    $replayItem->update(['unlock_rule' => 'none', 'unlock_at' => null]);
    app(EntitlementService::class)->flush();
    $this->actingAs($learner)
        ->get(route('learn.show', ['course' => $course->id, 'item' => $liveItem->id]))
        ->assertOk()
        ->assertSee('data-replay-link', false)
        ->assertSee(route('learn.show', ['course' => $course->id, 'item' => $replayItem->id]), false);
    $this->get(route('learn.show', ['course' => $course->id, 'item' => $replayItem->id]))->assertOk();

    $this->actingAs($stranger)
        ->get(route('learn.show', ['course' => $course->id, 'item' => $replayItem->id]))
        ->assertNotFound();
})->group('T-P5-05', 'A2');

it('drives attendance marking, correction validation, and replay publishing from ManageCurriculum', function (): void {
    [$course, $session, $admin] = repBuild();
    $learner1 = User::factory()->create(['status' => 'active'])->assignRole('student');
    $learner2 = User::factory()->create(['status' => 'active'])->assignRole('student');
    app(GrantEntitlement::class)($learner1, $course, EntitlementSource::Free);
    app(GrantEntitlement::class)($learner2, $course, EntitlementSource::Free);

    $this->actingAs($admin);
    $page = Livewire::test(ManageCurriculum::class, ['record' => $course->getKey()]);

    // Mark two learners
    $page->callAction('attendance', [
        'marks' => [$learner1->id => LiveAttendance::PRESENT, $learner2->id => LiveAttendance::LATE],
    ], arguments: ['session' => $session->id])->assertHasNoActionErrors();

    expect(LiveAttendance::query()->where('live_session_id', $session->id)->count())->toBe(2);

    // Changing mark without reason halts with danger notification and leaves mark untouched
    $page->callAction('attendance', [
        'marks' => [$learner1->id => LiveAttendance::ABSENT, $learner2->id => LiveAttendance::LATE],
        'reason' => '',
    ], arguments: ['session' => $session->id])->assertActionHalted('attendance')->assertNotified()->unmountAction();
    expect(LiveAttendance::query()->where('learner_id', $learner1->id)->value('status'))->toBe(LiveAttendance::PRESENT);

    // Changing mark with valid reason succeeds
    $page->callAction('attendance', [
        'marks' => [$learner1->id => LiveAttendance::ABSENT, $learner2->id => LiveAttendance::LATE],
        'reason' => 'Confirmed did not attend',
    ], arguments: ['session' => $session->id])->assertHasNoActionErrors();
    expect(LiveAttendance::query()->where('learner_id', $learner1->id)->value('status'))->toBe(LiveAttendance::ABSENT);

    // Publish replay via ManageCurriculum
    $page->call('publishLiveReplay', $session->id)->assertNotified();
    expect($session->refresh()->replay_item_id)->not->toBeNull();
})->group('T-P5-05', 'A1', 'A2');
