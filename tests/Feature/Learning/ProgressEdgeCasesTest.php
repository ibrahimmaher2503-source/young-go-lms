<?php

declare(strict_types=1);

// T-P4-04 | PRD A8, ED-025, O-020 | A1 the server decides completion, A2 resume and next lesson, A3 percentages follow today's required lessons

use App\Domain\Curriculum\Actions\ManageAssessmentItems;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Progress\Actions\RecalculateCourseProgress;
use App\Domain\Progress\Actions\RecordProgress;
use App\Domain\Progress\CourseProgress;
use App\Domain\Progress\LessonCompletionLookup;
use App\Domain\Progress\LessonProgress;
use App\Domain\Progress\ProgressHistory;
use App\Domain\Progress\Queries\ContinueLearning;
use App\Domain\Progress\Queries\LearnerCourseDetail;
use App\Domain\Progress\Queries\LearnerCourseRows;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\CurriculumItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2027-03-10 10:00:00', 'UTC'));
});

/** @return array{0: Course, 1: array<string, CurriculumItem>, 2: User, 3: User, 4: CourseSection} */
function peBuild(bool $enroll = true): array
{
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $course = Course::query()->create(['title' => ['en' => 'Progress edges', 'ar' => 'حدود التقدم'], 'slug_ar' => 'pe-'.uniqid(), 'slug_en' => 'pe-'.uniqid(), 'status' => 'published', 'access_mode' => 'free']);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'S']]);
    $section->update(['published' => true]);
    $video = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Video'], 'type' => 'video', 'duration_seconds' => 100]);
    $text = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Reading'], 'type' => 'text']);
    $quiz = app(ManageAssessmentItems::class)->add($instructor, $section, 'quiz', ['title' => ['en' => 'Quiz']]);
    app(SetCurriculumStatus::class)($instructor, $course, [$video->id, $text->id, $quiz->id], 'published');

    $learner = User::factory()->create()->assignRole('student');
    if ($enroll) {
        app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);
    }

    return [$course->refresh(), ['video' => $video->refresh(), 'text' => $text->refresh(), 'quiz' => $quiz->refresh()], $learner, $instructor, $section];
}

/** @return array{0: Course, 1: User, 2: User} a published free course with no lessons and an enrolled learner */
function peEmptyCourse(): array
{
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $course = Course::query()->create(['title' => ['en' => 'Empty', 'ar' => 'فارغ'], 'slug_ar' => 'pe-e-'.uniqid(), 'slug_en' => 'pe-e-'.uniqid(), 'status' => 'published', 'access_mode' => 'free']);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $learner = User::factory()->create()->assignRole('student');
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);

    return [$course->refresh(), $learner, $instructor];
}

it('never completes a video by jumping forward, then completes it once enough real time has passed', function (): void {
    [, $items, $learner] = peBuild();
    $record = app(RecordProgress::class);

    $row = $record->heartbeat($learner, $items['video'], -5);
    expect($row->position_seconds)->toBe(0)->and($row->furthest_seconds)->toBe(0)->and($row->percent)->toBe(0);

    $row = $record->heartbeat($learner, $items['video'], 500);
    expect($row->position_seconds)->toBe(0)->and($row->furthest_seconds)->toBe(0)->and($row->percent)->toBe(0)
        ->and($row->status)->toBe(LessonProgress::IN_PROGRESS);

    $this->travelTo(now()->addSeconds(40));
    $row = $record->heartbeat($learner, $items['video'], 500);
    expect($row->position_seconds)->toBe(100)->and($row->furthest_seconds)->toBe(100)->and($row->percent)->toBe(100)
        ->and($row->status)->toBe(LessonProgress::COMPLETED)->and($row->completion_source)->toBe('auto');
});

it('uses the configured completion percentage and speed', function (): void {
    [, $items, $learner] = peBuild();
    $record = app(RecordProgress::class);
    config()->set('learning.progress.complete_at_percent', 50);
    config()->set('learning.progress.max_speed', 1.0);

    $record->heartbeat($learner, $items['video'], 20);
    $this->travelTo(now()->addSeconds(10)); // startup tolerance is not granted again
    $row = $record->heartbeat($learner, $items['video'], 50);
    expect($row->furthest_seconds)->toBe(30)->and($row->status)->toBe(LessonProgress::IN_PROGRESS);

    $this->travelTo(now()->addSeconds(20));
    $row = $record->heartbeat($learner, $items['video'], 50);
    expect($row->furthest_seconds)->toBe(50)->and($row->percent)->toBe(50)->and($row->status)->toBe(LessonProgress::COMPLETED);
});

it('cannot judge a recording of unknown length and leaves it in progress', function (): void {
    [, $items, $learner] = peBuild();
    $items['video']->itemable->update(['duration_seconds' => null]);

    $row = app(RecordProgress::class)->heartbeat($learner, $items['video']->refresh(), 300);

    expect($row->percent)->toBe(0)->and($row->status)->toBe(LessonProgress::IN_PROGRESS)
        ->and($row->position_seconds)->toBe(30)->and($row->furthest_seconds)->toBe(30);
});

it('allows a manual mark for an unknown-length recording only when configured', function (): void {
    [, $items, $learner] = peBuild();
    $items['video']->itemable->update(['duration_seconds' => null]);
    $record = app(RecordProgress::class);

    expect($record->markComplete($learner, $items['video']->refresh())->completion_source)->toBe('manual');

    config()->set('learning.progress.manual_when_duration_unknown', false);
    expect(fn () => $record->markComplete($learner, $items['video']->refresh()))->toThrow(ValidationException::class)
        ->and(fn () => $record->markComplete($learner, $items['text']->refresh()))->not->toThrow(ValidationException::class);
});

it('never lets an audio or video lesson with a known length be marked done by hand', function (): void {
    [$course, , $learner, $instructor, $section] = peBuild();
    $audio = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Audio'], 'type' => 'audio', 'duration_seconds' => 60]);
    app(SetCurriculumStatus::class)($instructor, $course, [$audio->id], 'published');

    expect(fn () => app(RecordProgress::class)->markComplete($learner, $audio->refresh()))->toThrow(ValidationException::class);
});

it('keeps the first completion time when a lesson is marked done again', function (): void {
    [, $items, $learner] = peBuild();
    $record = app(RecordProgress::class);

    $first = $record->markComplete($learner, $items['text']);
    $this->travelTo(now()->addDay());
    $again = $record->markComplete($learner, $items['text']->refresh());

    expect($again->status)->toBe(LessonProgress::COMPLETED)->and($again->completion_source)->toBe('manual')
        ->and($again->completed_at->equalTo($first->completed_at))->toBeTrue()
        ->and($again->percent)->toBe(100);
});

it('writes a zero percentage for a course with no required lessons', function (): void {
    [$course, $learner] = peEmptyCourse();

    $progress = app(RecalculateCourseProgress::class)($learner, $course);

    expect($progress->percent)->toBe(0)->and($progress->completed_required)->toBe(0)->and($progress->total_required)->toBe(0)
        ->and($progress->completed_at)->toBeNull()->and($progress->last_item_id)->toBeNull()
        ->and(CourseProgress::query()->where('course_id', $course->getKey())->count())->toBe(1);
});

it('drops a completed course back to zero when every required lesson stops counting', function (): void {
    [$course, , $learner, $instructor, $section] = peBuild();
    $only = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Only'], 'type' => 'text']);
    app(SetCurriculumStatus::class)($instructor, $course, [$only->id], 'published');
    CurriculumItem::query()->where('section_id', $section->getKey())->where('id', '!=', $only->getKey())->update(['required' => false]);
    $record = app(RecordProgress::class);
    $recalc = app(RecalculateCourseProgress::class);

    $record->markComplete($learner, $only->refresh());
    expect($recalc($learner, $course)->percent)->toBe(100)->and($recalc($learner, $course)->completed_at)->not->toBeNull();

    app(ManageLessons::class)->archive($instructor, $only->refresh()->itemable);

    $after = $recalc($learner, $course);
    expect($after->percent)->toBe(0)->and($after->total_required)->toBe(0)->and($after->completed_at)->toBeNull()
        ->and(LessonProgress::query()->where('status', LessonProgress::COMPLETED)->count())->toBe(1);
});

it('keeps the course completion time stable across later recalculations', function (): void {
    [$course, , $learner, $instructor, $section] = peBuild();
    $only = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Only'], 'type' => 'text']);
    app(SetCurriculumStatus::class)($instructor, $course, [$only->id], 'published');
    CurriculumItem::query()->where('section_id', $section->getKey())->where('id', '!=', $only->getKey())->update(['required' => false]);
    app(RecordProgress::class)->markComplete($learner, $only->refresh());
    $recalc = app(RecalculateCourseProgress::class);
    $first = $recalc($learner, $course)->completed_at;

    $this->travelTo(now()->addWeek());
    $second = $recalc($learner, $course);

    expect($first)->not->toBeNull()->and($second->completed_at->equalTo($first))->toBeTrue();
});

it('finds only this learner and course in the completion lookup', function (): void {
    [$course, $items, $learner] = peBuild();
    $other = User::factory()->create()->assignRole('student');
    $otherCourse = Course::query()->create(['title' => ['en' => 'Elsewhere'], 'slug_ar' => 'pe-o-'.uniqid(), 'slug_en' => 'pe-o-'.uniqid(), 'status' => 'published']);
    $otherItem = CurriculumItem::query()->create(['course_id' => $otherCourse->getKey(), 'section_id' => $items['video']->section_id, 'sort' => 1, 'itemable_type' => 'lesson', 'itemable_id' => 999, 'status' => 'published']);
    app(RecordProgress::class)->markComplete($learner, $items['text']);
    LessonProgress::query()->create(['learner_id' => $learner->getKey(), 'curriculum_item_id' => $otherItem->getKey(), 'status' => LessonProgress::COMPLETED, 'percent' => 100, 'position_seconds' => 0, 'furthest_seconds' => 0]);
    LessonProgress::query()->create(['learner_id' => $other->getKey(), 'curriculum_item_id' => $items['video']->getKey(), 'status' => LessonProgress::COMPLETED, 'percent' => 100, 'position_seconds' => 0, 'furthest_seconds' => 0]);

    expect(app(LessonCompletionLookup::class)->completedItemIds($learner, $course))->toBe([$items['text']->getKey()]);
});

it('reports course history only when a progress row exists', function (): void {
    [$course, $items, $learner] = peBuild();
    $history = app(ProgressHistory::class);

    expect($history->blocks($course))->toBeNull();

    LessonProgress::query()->create(['learner_id' => $learner->getKey(), 'curriculum_item_id' => $items['text']->getKey(), 'status' => LessonProgress::IN_PROGRESS, 'percent' => 10, 'position_seconds' => 0, 'furthest_seconds' => 0]);
    expect($history->blocks($course))->toBe('progress');

    LessonProgress::query()->delete();
    CourseProgress::query()->create(['learner_id' => $learner->getKey(), 'course_id' => $course->getKey(), 'percent' => 0, 'completed_required' => 0, 'total_required' => 0]);
    expect($history->blocks($course))->toBe('progress');
});

it('describes each lesson state for staff without including quizzes or other learners', function (): void {
    [$course, $items, $learner] = peBuild();
    app(RecordProgress::class)->heartbeat($learner, $items['video'], 30);

    $detail = LearnerCourseDetail::for($learner, $course);

    expect($detail)->toHaveCount(2)
        ->and($detail[0])->toMatchArray(['title' => 'Video', 'type' => 'video', 'status' => LessonProgress::IN_PROGRESS, 'percent' => 30])
        ->and($detail[0]['last_at'])->not->toBeNull()
        ->and($detail[1])->toMatchArray(['title' => 'Reading', 'type' => 'text', 'status' => 'not_started', 'percent' => 0, 'last_at' => null]);
});

it('shows active, completed and expired courses with their real progress and next lesson', function (): void {
    [$course, $items, $learner] = peBuild();
    $record = app(RecordProgress::class);
    $rows = app(LearnerCourseRows::class);

    $record->heartbeat($learner, $items['video'], 20);
    $row = $rows->for($learner)->sole();
    expect($row['status'])->toBe('in_progress')->and($row['done'])->toBe(0)->and($row['total'])->toBe(2)
        ->and($row['continue'])->toMatchArray(['item_id' => $items['video']->getKey(), 'position_seconds' => 20]);

    $this->travelTo(now()->addSeconds(40));
    $record->heartbeat($learner, $items['video'], 500);
    $record->markComplete($learner, $items['text']->refresh());
    $row = $rows->for($learner)->sole();
    expect($row['status'])->toBe('completed')->and($row['percent'])->toBe(100)->and($row['done'])->toBe(2)->and($row['total'])->toBe(2);

    $ended = Course::query()->create(['title' => ['en' => 'Ended'], 'slug_ar' => 'pe-x-'.uniqid(), 'slug_en' => 'pe-x-'.uniqid(), 'status' => 'published']);
    app(GrantEntitlement::class)($learner, $ended, EntitlementSource::Free, '', now()->subDays(3), now()->subDay());
    $expired = $rows->for($learner)->first(fn (array $r): bool => $r['course']->is($ended));
    expect($expired['status'])->toBe('expired')->and($expired['percent'])->toBe(0)->and($expired['done'])->toBe(0)
        ->and($expired['total'])->toBe(0)->and($expired['continue'])->toBeNull();
});

it('continues nowhere when every open item is drip locked', function (): void {
    [$course, , $learner, $instructor, $section] = peBuild();
    $only = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Later'], 'type' => 'text']);
    app(SetCurriculumStatus::class)($instructor, $course, [$only->id], 'published');
    CurriculumItem::query()->where('section_id', $section->getKey())->where('id', '!=', $only->getKey())->update(['status' => 'draft']);
    $only->refresh()->update(['unlock_rule' => 'date', 'unlock_at' => now()->addWeek()]);

    expect(app(ContinueLearning::class)->for($learner, $course))->toBeNull();
});

it('falls back to the first open lesson when the last one is completed or became locked', function (): void {
    [$course, $items, $learner] = peBuild();
    $continue = app(ContinueLearning::class);
    app(RecordProgress::class)->markComplete($learner, $items['text']);

    expect($continue->for($learner, $course))->toBe(['item_id' => $items['video']->getKey(), 'position_seconds' => 0]);

    app(RecordProgress::class)->heartbeat($learner, $items['video'], 15);
    $resume = $continue->for($learner, $course);
    expect($resume['position_seconds'])->toBe(15);

    $items['video']->refresh()->update(['unlock_rule' => 'date', 'unlock_at' => now()->addWeek()]);
    $fallback = $continue->for($learner, $course);
    expect($fallback['position_seconds'])->toBe(0)->and($fallback['item_id'])->not->toBe($items['video']->getKey());
});

it('refuses new progress once the entitlement has ended but keeps what was recorded', function (): void {
    [$course, , $learner, $instructor, $section] = peBuild(enroll: false);
    $only = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'One'], 'type' => 'text']);
    app(SetCurriculumStatus::class)($instructor, $course, [$only->id], 'published');
    CurriculumItem::query()->where('section_id', $section->getKey())->where('id', '!=', $only->getKey())->update(['required' => false]);
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free, '', now()->subDay(), now()->addDay());
    $record = app(RecordProgress::class);
    $record->markComplete($learner, $only->refresh());

    $this->travelTo(now()->addDays(2));
    app(EntitlementService::class)->flush();

    expect(fn () => $record->markComplete($learner, $only->refresh()))->toThrow(AuthorizationException::class)
        ->and(fn () => $record->heartbeat($learner, $only->refresh(), 10))->toThrow(AuthorizationException::class)
        ->and(LessonProgress::query()->where('status', LessonProgress::COMPLETED)->count())->toBe(1);
});
