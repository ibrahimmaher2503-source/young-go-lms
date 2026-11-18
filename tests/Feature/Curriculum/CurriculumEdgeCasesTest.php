<?php

declare(strict_types=1);

// T-P3-04 | YG-GAP-CNT-001, ED-002, ED-024, ED-031 | A1 ordering and visibility, A2 publish rules, A3 authoring validation, A4 audit

use App\Domain\Content\RichContent;
use App\Domain\Curriculum\Actions\ManageAssessmentItems;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\ReorderCurriculum;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Curriculum\Queries\CurriculumOutline;
use App\Domain\Curriculum\Queries\EntitledCurriculumQuery;
use App\Domain\Media\Actions\AttachLessonMedia;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    config()->set('media.iframe_allowlist', ['player.example.com']);
});

/** @return array{0: User, 1: Course, 2: CourseSection} */
function cxBuild(string $access = 'free'): array
{
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $course = Course::query()->create([
        'title' => ['en' => 'Edge'], 'slug_ar' => 'cx-'.uniqid(), 'slug_en' => 'cx-'.uniqid(),
        'access_mode' => $access, 'sequence_mode' => 'free',
    ]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'S']]);

    return [$instructor, $course, $section];
}

function cxLesson(User $instructor, CourseSection $section, string $title): CurriculumItem
{
    return app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => $title]]);
}

it('trims titles and cleans rich content down to the allowlist', function (): void {
    [$instructor, , $section] = cxBuild();
    $section = app(ManageSections::class)->rename($instructor, $section, ['title' => ['en' => '  One  ', 'ar' => '   ']]);

    $item = app(ManageLessons::class)->add($instructor, $section, [
        'title' => ['en' => '  Lesson  '],
        'description' => ['en' => '   ', 'ar' => '  شرح  '],
        'content' => ['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'attrs' => ['onclick' => 'steal()'], 'content' => [
                ['type' => 'text', 'text' => 'Danger', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']], ['type' => 'bold']]],
                ['type' => 'text', 'text' => 'Safe', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://example.org/guide']]]],
            ]],
            ['type' => 'script', 'content' => []],
        ]],
    ]);
    $lesson = $item->itemable->refresh();

    expect($section->refresh()->getTranslations('title'))->toBe(['en' => 'One'])
        ->and($lesson->getTranslations('title'))->toBe(['en' => 'Lesson'])
        ->and($lesson->getTranslations('description'))->toBe(['ar' => 'شرح'])
        ->and($lesson->content['content'])->toHaveCount(1)
        ->and($lesson->content['content'][0]['content'][0]['marks'])->toBe([['type' => 'bold']])
        ->and($lesson->content['content'][0]['content'][1]['marks'][0]['attrs']['href'])->toBe('https://example.org/guide')
        ->and(json_encode($lesson->content))->not->toContain('javascript')
        ->and((string) RichContent::html($lesson->content))->not->toContain('script');
});

it('stores empty rich content as null and normalizes blank descriptions and durations', function (): void {
    [$instructor, , $section] = cxBuild();

    $empty = app(ManageLessons::class)->add($instructor, $section, [
        'title' => ['en' => 'Empty'], 'content' => [], 'description' => ['en' => '  '], 'duration_seconds' => 86400,
    ])->itemable->refresh();

    expect($empty->content)->toBeNull()->and($empty->getTranslations('description'))->toBe([])->and($empty->duration_seconds)->toBe(86400);

    $max = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Max'], 'duration_seconds' => 86400]);
    expect($max->itemable->duration_seconds)->toBe(86400);

    foreach ([86401, -1] as $seconds) {
        expect(fn () => app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Bad'], 'duration_seconds' => $seconds]))
            ->toThrow(ValidationException::class);
    }
    expect(fn () => app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Raw'], 'content' => '<p>hi</p>']))
        ->toThrow(ValidationException::class);
});

it('refuses a lesson whose curriculum item was removed', function (): void {
    [$instructor, , $section] = cxBuild();
    $item = cxLesson($instructor, $section, 'Orphan');
    $lesson = $item->itemable;
    $item->delete();

    expect(fn () => app(ManageLessons::class)->update($instructor, $lesson, ['title' => ['en' => 'Renamed']]))->toThrow(NotFoundHttpException::class)
        ->and(fn () => app(ManageLessons::class)->archive($instructor, $lesson))->toThrow(NotFoundHttpException::class)
        ->and(Lesson::query()->find($lesson->getKey())->archived_at)->toBeNull();
});

it('publishes an empty section and records the zero item count', function (): void {
    [$instructor, , $section] = cxBuild();

    app(ManageSections::class)->setPublished($instructor, $section, true);

    $entry = Activity::query()->where('event', 'curriculum.section_published')->sole();

    expect($section->refresh()->published)->toBeTrue()
        ->and($entry->properties->get('after'))->toBe(['items' => 0]);
});

it('leaves archived lessons alone when a section is published or unpublished', function (): void {
    [$instructor, $course, $section] = cxBuild();
    $archived = cxLesson($instructor, $section, 'Archived');
    app(SetCurriculumStatus::class)($instructor, $course, [$archived->id], 'published');
    app(ManageLessons::class)->archive($instructor, $archived->itemable);
    $live = cxLesson($instructor, $section, 'Live');

    app(ManageSections::class)->setPublished($instructor, $section, true);
    expect($archived->refresh()->status)->toBe('published')->and($live->refresh()->status)->toBe('published');

    app(ManageSections::class)->setPublished($instructor, $section, false);
    expect($archived->refresh()->status)->toBe('published')->and($live->refresh()->status)->toBe('draft')
        ->and(CurriculumOutline::studentOrder($course))->toBe([]);
});

it('rolls back a whole section publish when one paid lesson uses an open source', function (): void {
    [$instructor, , $section] = cxBuild('paid');
    $blocked = cxLesson($instructor, $section, 'Open source');
    $fine = cxLesson($instructor, $section, 'No media');
    app(AttachLessonMedia::class)($instructor, $blocked->itemable, ['provider' => 'youtube', 'url' => 'https://youtu.be/dQw4w9WgXcQ']);

    expect(fn () => app(ManageSections::class)->setPublished($instructor, $section, true))->toThrow(ValidationException::class);

    expect($section->refresh()->published)->toBeFalse()
        ->and($blocked->refresh()->status)->toBe('draft')
        ->and($fine->refresh()->status)->toBe('draft');

    app(ManageLessons::class)->update($instructor, $blocked->itemable, ['title' => ['en' => 'Open source'], 'is_preview' => true]);
    app(ManageSections::class)->setPublished($instructor, $section, true);
    expect($section->refresh()->published)->toBeTrue()->and($blocked->refresh()->status)->toBe('published');
});

it('deduplicates bulk ids and rejects empty, unknown or foreign selections without partial writes', function (): void {
    [$instructor, $course, $section] = cxBuild();
    $a = cxLesson($instructor, $section, 'A');
    $b = cxLesson($instructor, $section, 'B');
    [, , $otherSection] = cxBuild();
    $foreign = cxLesson(User::factory()->create()->assignRole('admin'), $otherSection, 'Foreign');

    expect(app(SetCurriculumStatus::class)($instructor, $course, [$a->id, $a->id, $b->id], 'published'))->toBe(2);

    foreach ([[], ['abc'], [$a->id, $foreign->id]] as $ids) {
        expect(fn () => app(SetCurriculumStatus::class)($instructor, $course, $ids, 'published'))->toThrow(ValidationException::class);
    }
    expect(fn () => app(SetCurriculumStatus::class)($instructor, $course, [$a->id], 'archived'))->toThrow(ValidationException::class);
    expect($a->refresh()->status)->toBe('published')->and($foreign->refresh()->status)->toBe('draft')->and($b->refresh()->status)->toBe('published');
});

it('reorders active sections and items while ignoring archived sections entirely', function (): void {
    [$instructor, $course, $one] = cxBuild();
    $two = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'Two']]);
    $a = cxLesson($instructor, $one, 'A');
    $b = cxLesson($instructor, $one, 'B');
    $c = cxLesson($instructor, $two, 'C');
    app(ManageSections::class)->archive($instructor, $two);

    app(ReorderCurriculum::class)($instructor, $course, [['section_id' => $one->id, 'items' => [$b->id, $a->id]]]);

    expect($one->refresh()->sort)->toBe(1)->and($b->refresh()->sort)->toBe(1)->and($a->refresh()->sort)->toBe(2)
        ->and($c->refresh()->section_id)->toBe($two->id)->and($c->sort)->toBe(1)
        ->and(CurriculumOutline::forManager($course)->pluck('id')->all())->toBe([$one->id]);

    expect(fn () => app(ReorderCurriculum::class)($instructor, $course, [
        ['section_id' => $one->id, 'items' => [$b->id, $a->id]],
        ['section_id' => $two->id, 'items' => [$c->id]],
    ]))->toThrow(ValidationException::class);
});

it('marks drafts and unpublished sections in the staff learner preview', function (): void {
    [$instructor, $course, $section] = cxBuild();
    $published = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'A'], 'type' => 'audio', 'duration_seconds' => 61]);
    $draft = cxLesson($instructor, $section, 'B');

    $preview = collect(app(EntitledCurriculumQuery::class)->forPreview($course))->keyBy('id');
    expect($preview[$published->id]['draft'])->toBeTrue()->and($preview[$draft->id]['draft'])->toBeTrue()
        ->and($preview[$published->id]['minutes'])->toBe(1)->and($preview[$published->id]['type'])->toBe('audio');

    app(SetCurriculumStatus::class)($instructor, $course, [$published->id, $draft->id], 'published');
    app(ManageSections::class)->setPublished($instructor, $section, true);
    app(SetCurriculumStatus::class)($instructor, $course, [$draft->id], 'draft');

    $preview = collect(app(EntitledCurriculumQuery::class)->forPreview($course))->keyBy('id');
    expect($preview[$published->id]['draft'])->toBeFalse()->and($preview[$draft->id]['draft'])->toBeTrue()
        ->and($preview[$published->id]['state'])->toBe('open');

    app(ManageLessons::class)->archive($instructor, $draft->itemable);
    expect(collect(app(EntitledCurriculumQuery::class)->forPreview($course))->pluck('id')->all())->toBe([$published->id]);
});

it('finds previous and next neighbours across locked items only', function (): void {
    $sections = [[
        'id' => 1,
        'title' => 'S',
        'items' => [
            ['id' => 10, 'state' => 'locked'], ['id' => 11, 'state' => 'open'],
            ['id' => 12, 'state' => 'locked'], ['id' => 13, 'state' => 'open'],
            ['id' => 14, 'state' => 'preview'],
        ],
    ]];

    expect(EntitledCurriculumQuery::neighbours($sections, 11))->toBe(['previous' => null, 'next' => 13])
        ->and(EntitledCurriculumQuery::neighbours($sections, 12))->toBe(['previous' => 11, 'next' => 13])
        ->and(EntitledCurriculumQuery::neighbours($sections, 14))->toBe(['previous' => 13, 'next' => null])
        ->and(EntitledCurriculumQuery::neighbours($sections, 999))->toBe(['previous' => null, 'next' => null])
        ->and(EntitledCurriculumQuery::neighbours([], 11))->toBe(['previous' => null, 'next' => null]);
});

it('keeps assessment titles exactly as typed', function (): void {
    [$instructor, , $section] = cxBuild();
    $action = app(ManageAssessmentItems::class);

    $blankLocale = $action->add($instructor, $section, 'quiz', ['title' => ['ar' => '   ', 'en' => 'Quiz']]);
    $zero = $action->add($instructor, $section, 'assignment', ['title' => ['ar' => '0', 'en' => '']]);

    expect($blankLocale->itemable->getTranslations('title'))->toBe(['en' => 'Quiz'])
        ->and($zero->itemable->getTranslations('title'))->toBe(['ar' => '0']);
});

it('refuses to archive a lesson through the assessment action', function (): void {
    [$instructor, , $section] = cxBuild();
    $item = cxLesson($instructor, $section, 'Lesson');

    expect(fn () => app(ManageAssessmentItems::class)->archive($instructor, $item))->toThrow(NotFoundHttpException::class)
        ->and($item->itemable->refresh()->archived_at)->toBeNull();
});
