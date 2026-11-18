<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\ArchiveCourse;
use App\Domain\Catalog\Actions\DeleteCourse;
use App\Domain\Catalog\Actions\DuplicateCourse;
use App\Domain\Catalog\CourseStatus;
use App\Domain\Curriculum\Actions\ManageAssessmentItems;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\Entitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Progress\Actions\RecordProgress;
use App\Domain\Progress\CourseProgress;
use App\Filament\Resources\Courses\Pages\ListCourses;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use App\Models\LessonCurriculumDocument;
use App\Models\LessonResource;
use App\Models\MediaAsset;
use App\Models\Quiz;
use App\Models\SubtitleTrack;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
    Storage::fake('local');
});

/** @return array{0: Course, 1: User, 2: User} a published course with a video lesson (media, material, caption, approved PDF), a reading lesson and a quiz */
function clBuilt(string $suffix = ''): array
{
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $admin = User::factory()->create()->assignRole('admin');
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة الرسم', 'en' => 'Drawing'], 'slug_ar' => 'drawing-ar'.$suffix, 'slug_en' => 'drawing'.$suffix, 'status' => 'published', 'access_mode' => 'paid',
        'featured' => true, 'faqs' => [['q' => 'Q', 'a' => 'A']],
    ]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'Basics', 'ar' => 'الأساسيات']]);
    $section->update(['published' => true]);

    Storage::disk('local')->put('media/clip.mp4', 'video');
    Storage::disk('local')->put('resources/notes.txt', 'notes');
    Storage::disk('local')->put('subtitles/en.vtt', "WEBVTT\n\n00:00:01.000 --> 00:00:02.000\nHi\n");
    Storage::disk('local')->put('documents/lesson.pdf', '%PDF-fake');

    $video = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Video lesson'], 'type' => 'video', 'duration_seconds' => 600]);
    $lesson = $video->itemable;
    MediaAsset::query()->create(['lesson_id' => $lesson->id, 'provider' => 'signed_storage', 'kind' => 'video', 'path' => 'media/clip.mp4', 'protection_tier' => 'T1', 'status' => 'ready']);
    LessonResource::query()->create(['lesson_id' => $lesson->id, 'type' => 'file', 'title' => ['en' => 'Notes'], 'path' => 'resources/notes.txt', 'mime' => 'text/plain', 'downloadable' => true, 'visibility' => 'enrolled', 'sort' => 1]);
    SubtitleTrack::query()->create(['lesson_id' => $lesson->id, 'locale' => 'en', 'label' => 'English', 'path' => 'subtitles/en.vtt', 'sha256' => str_repeat('b', 64), 'size' => 10, 'is_default' => true]);
    LessonCurriculumDocument::query()->create(['lesson_id' => $lesson->id, 'version' => 1, 'path' => 'documents/lesson.pdf', 'sha256' => str_repeat('a', 64), 'size' => 9, 'pages' => 2, 'status' => 'ready', 'active' => true]);

    $reading = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Reading'], 'type' => 'text']);
    app(ManageLessons::class)->update($instructor, $reading->itemable, ['title' => ['en' => 'Reading'], 'content' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hello']]]]]]);
    $quiz = app(ManageAssessmentItems::class)->add($instructor, $section, 'quiz', [
        'title' => ['en' => 'Quiz'], 'association' => 'lesson', 'associated_lesson_item_id' => $reading->id,
    ]);
    app(SetCurriculumStatus::class)($instructor, $course, [$video->id, $reading->id, $quiz->id], 'published');

    return [$course->refresh(), $admin, $instructor];
}

it('duplicates a course into an independent draft with copied files and no learner data', function (): void {
    [$course, $admin, $instructor] = clBuilt();
    $learner = User::factory()->create()->assignRole('student');
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);
    $items = CurriculumItem::query()->where('course_id', $course->id)->orderBy('id')->get();
    app(RecordProgress::class)->markComplete($learner, $items[1]->refresh());

    $copy = app(DuplicateCourse::class)($admin, $course);

    expect($copy->id)->not->toBe($course->id)->and($copy->status)->toBe(CourseStatus::Draft)->and($copy->featured)->toBeFalse()
        ->and($copy->getTranslation('title', 'en'))->toBe('Drawing (copy)')->and($copy->getTranslation('title', 'ar'))->toBe('دورة الرسم (نسخة)')
        ->and($copy->slug_en)->toBe('drawing-copy')->and($copy->slug_ar)->toBe('drawing-ar-copy')->and($copy->wizard_completed)->toBe([])
        ->and($copy->faqs)->toBe([['q' => 'Q', 'a' => 'A']])->and($copy->instructors->pluck('id')->all())->toBe([$instructor->id]);

    $newItems = CurriculumItem::query()->where('course_id', $copy->id)->orderBy('sort')->orderBy('id')->get();
    expect($newItems)->toHaveCount(3)->and($newItems->pluck('status')->unique()->all())->toBe(['draft'])
        ->and($newItems->pluck('itemable_type')->all())->toBe(['lesson', 'lesson', 'quiz'])
        ->and($newItems[2]->association)->toBe('lesson')
        ->and($newItems[2]->associated_lesson_item_id)->toBe($newItems[1]->id)
        ->and(CourseSection::query()->where('course_id', $copy->id)->first()->published)->toBeFalse();
    $oldLessonIds = $items->where('itemable_type', 'lesson')->pluck('itemable_id');
    expect($newItems->where('itemable_type', 'lesson')->pluck('itemable_id')->intersect($oldLessonIds))->toBeEmpty()
        ->and(Quiz::query()->count())->toBe(2)->and(Lesson::query()->count())->toBe(4);

    // Learner data stays with the original.
    expect(Entitlement::query()->where('course_id', $copy->id)->count())->toBe(0)->and(Entitlement::query()->where('course_id', $course->id)->count())->toBe(1);

    // Files were copied, not shared.
    $newVideo = Lesson::query()->find($newItems[0]->itemable_id);
    $newMedia = MediaAsset::query()->where('lesson_id', $newVideo->id)->first();
    $newResource = LessonResource::query()->where('lesson_id', $newVideo->id)->first();
    $newTrack = SubtitleTrack::query()->where('lesson_id', $newVideo->id)->first();
    foreach ([$newMedia->path, $newResource->path, $newTrack->path] as $path) {
        Storage::disk('local')->assertExists($path);
    }
    expect($newMedia->path)->not->toBe('media/clip.mp4')->and($newResource->path)->not->toBe('resources/notes.txt')->and($newTrack->path)->not->toBe('subtitles/en.vtt')
        ->and($newResource->getTranslation('title', 'en'))->toBe('Notes');
    $newReading = Lesson::query()->find($newItems[1]->itemable_id);
    expect($newReading->content['content'][0]['content'][0]['text'])->toBe('Hello');

    // The curriculum PDF is copied as a fresh upload that is not active and must be approved again.
    $newDocument = LessonCurriculumDocument::query()->where('lesson_id', $newVideo->id)->first();
    expect($newDocument->status)->toBe(LessonCurriculumDocument::STATUS_UPLOADED)->and($newDocument->active)->toBeFalse()->and($newDocument->approved_at)->toBeNull()
        ->and($newVideo->activeCurriculumDocument())->toBeNull()->and($newDocument->path)->not->toBe('documents/lesson.pdf');
    Storage::disk('local')->assertExists($newDocument->path);

    // Independent: changing or deleting the copy never touches the original.
    $newVideo->update(['duration_seconds' => 5]);
    app(ManageLessons::class)->update($instructor, $items[1]->itemable, ['title' => ['en' => 'Reading renamed']]);
    expect(Lesson::query()->find($items[0]->itemable_id)->duration_seconds)->toBe(600)->and(Lesson::query()->find($newItems[1]->itemable_id)->getTranslation('title', 'en'))->toBe('Reading');
    app(DeleteCourse::class)($admin, $copy);
    Storage::disk('local')->assertExists('media/clip.mp4');
    Storage::disk('local')->assertMissing($newMedia->path);
    expect($course->refresh()->status)->toBe(CourseStatus::Published)->and(Activity::query()->where('event', 'course.duplicated')->count())->toBe(1);
})->group('T-P3-12', 'A1');

it('makes unique slugs for repeated copies and skips archived lessons', function (): void {
    [$course, $admin, $instructor] = clBuilt();
    $item = CurriculumItem::query()->where('course_id', $course->id)->where('itemable_type', 'lesson')->orderBy('id')->first();
    app(ManageLessons::class)->archive($instructor, $item->itemable);

    $one = app(DuplicateCourse::class)($admin, $course);
    $two = app(DuplicateCourse::class)($admin, $course);

    expect($one->slug_en)->toBe('drawing-copy')->and($two->slug_en)->toBe('drawing-copy-2')->and(CurriculumItem::query()->where('course_id', $one->id)->count())->toBe(2);
})->group('T-P3-12', 'A1');

it('only lets people with create rights duplicate', function (): void {
    [$course, , $instructor] = clBuilt();

    expect(fn () => app(DuplicateCourse::class)($instructor, $course))->toThrow(AuthorizationException::class)
        ->and(fn () => app(DuplicateCourse::class)(User::factory()->create()->assignRole('student'), $course))->toThrow(AuthorizationException::class);
})->group('T-P3-12', 'A1');

it('blocks deletion when access or progress history exists and archives instead', function (): void {
    [$course, $admin] = clBuilt();
    $learner = User::factory()->create()->assignRole('student');
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);

    expect(DeleteCourse::blockers($course))->toBe(['entitlements']);
    expect(fn () => app(DeleteCourse::class)($admin, $course))->toThrow(ValidationException::class);
    expect(Course::query()->whereKey($course->id)->exists())->toBeTrue()->and(CurriculumItem::query()->where('course_id', $course->id)->count())->toBe(3);
    Storage::disk('local')->assertExists('media/clip.mp4');

    $archived = app(ArchiveCourse::class)($admin, $course);
    expect($archived->status)->toBe(CourseStatus::Archived)->and($archived->featured)->toBeFalse()
        ->and(app(ArchiveCourse::class)($admin, $course)->status)->toBe(CourseStatus::Archived)
        ->and(Activity::query()->where('event', 'course.archived')->count())->toBe(1);

    $other = clBuilt('-two')[0];
    $reader = User::factory()->create()->assignRole('student');
    // progress alone (without an entitlement row, for example after a data repair) also blocks
    CourseProgress::query()->create(['learner_id' => $reader->id, 'course_id' => $other->id]);
    expect(DeleteCourse::blockers($other))->toBe(['progress']);
})->group('T-P3-12', 'A2');

it('deletes a course nobody ever used, with its rows and files, and audits it', function (): void {
    [$course, $admin, $instructor] = clBuilt();

    expect(DeleteCourse::blockers($course))->toBe([]);
    expect(fn () => app(DeleteCourse::class)($instructor, $course))->toThrow(AuthorizationException::class);

    app(DeleteCourse::class)($admin, $course);

    expect(Course::query()->count())->toBe(0)->and(CourseSection::query()->count())->toBe(0)->and(CurriculumItem::query()->count())->toBe(0)
        ->and(Lesson::query()->count())->toBe(0)->and(Quiz::query()->count())->toBe(0)->and(MediaAsset::query()->count())->toBe(0)
        ->and(LessonCurriculumDocument::query()->count())->toBe(0)->and(SubtitleTrack::query()->count())->toBe(0)->and(LessonResource::query()->count())->toBe(0);
    foreach (['media/clip.mp4', 'resources/notes.txt', 'subtitles/en.vtt', 'documents/lesson.pdf'] as $path) {
        Storage::disk('local')->assertMissing($path);
    }
    expect(Activity::query()->where('event', 'course.deleted')->count())->toBe(1);
})->group('T-P3-12', 'A2');

it('offers duplicate, archive and delete in the course list by permission', function (): void {
    [$course, $admin, $instructor] = clBuilt();

    $this->actingAs($admin);
    Livewire::test(ListCourses::class)->assertTableActionVisible('duplicate', $course)->assertTableActionVisible('archive', $course)->assertTableActionVisible('delete', $course)
        ->callTableAction('duplicate', $course)->assertNotified();
    expect(Course::query()->count())->toBe(2);

    Livewire::test(ListCourses::class)->callTableAction('archive', $course)->assertNotified();
    expect($course->refresh()->status)->toBe(CourseStatus::Archived);
    Livewire::test(ListCourses::class)->assertTableActionHidden('archive', $course);

    $this->actingAs($instructor);
    Livewire::test(ListCourses::class)->assertTableActionHidden('duplicate', $course)->assertTableActionHidden('delete', $course);

    $this->actingAs($admin);
    $learner = User::factory()->create()->assignRole('student');
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);
    Livewire::test(ListCourses::class)->callTableAction('delete', $course)->assertNotified(); // blocked: the notification explains it
    expect(Course::query()->whereKey($course->id)->exists())->toBeTrue();
})->group('T-P3-12', 'A1', 'A2');
