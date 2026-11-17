<?php

declare(strict_types=1);

namespace App\Domain\Curriculum\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Curriculum\CurriculumAccess;
use App\Domain\Curriculum\Queries\CurriculumOutline;
use App\Domain\Media\LessonPublishRule;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\CurriculumItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Add, rename, publish and archive sections. Every method authorizes the actor against the section's course. */
final class ManageSections
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function add(User $actor, Course $course, array $data): CourseSection
    {
        CurriculumAccess::authorize($actor, $course, CurriculumAccess::CREATE);
        $title = CurriculumAccess::validTitle($data);

        return DB::transaction(static function () use ($course, $title): CourseSection {
            Course::query()->whereKey($course->getKey())->lockForUpdate()->first();
            $sort = (int) CourseSection::query()->where('course_id', $course->getKey())->max('sort') + 1;

            return CourseSection::query()->create(['course_id' => $course->getKey(), 'title' => $title, 'sort' => $sort, 'published' => false]);
        });
    }

    /** @param array<string, mixed> $data */
    public function rename(User $actor, CourseSection $section, array $data): CourseSection
    {
        CurriculumAccess::authorize($actor, $section->course);
        $section->update(['title' => CurriculumAccess::validTitle($data)]);

        return $section;
    }

    /** Publishing a section publishes every active item in it; unpublishing hides it from students. Audited. */
    public function setPublished(User $actor, CourseSection $section, bool $published): void
    {
        CurriculumAccess::authorize($actor, $section->course);

        DB::transaction(function () use ($actor, $section, $published): void {
            $status = $published ? CurriculumItem::STATUS_PUBLISHED : CurriculumItem::STATUS_DRAFT;
            $ids = CurriculumOutline::activeItems(CurriculumItem::query()->where('section_id', $section->getKey()))->pluck('id')->all();
            if ($published) {
                LessonPublishRule::assertPublishable(CurriculumItem::query()->whereKey($ids)->with(['itemable', 'course'])->get());
            }
            $section->update(['published' => $published]);
            CurriculumItem::query()->whereKey($ids)->update(['status' => $status]);

            $recorded = $this->audit->record($published ? 'curriculum.section_published' : 'curriculum.section_unpublished', $section, (int) $actor->getKey(), null, ['items' => count($ids)]);
            if ($recorded === null) {
                throw new RuntimeException('Unable to record curriculum audit.');
            }
        });
    }

    /** Archiving hides the section from students but keeps it and its items for history. */
    public function archive(User $actor, CourseSection $section): void
    {
        CurriculumAccess::authorize($actor, $section->course, CurriculumAccess::DELETE);

        DB::transaction(function () use ($actor, $section): void {
            $section->update(['archived_at' => now()]);
            if ($this->audit->record('curriculum.section_archived', $section, (int) $actor->getKey()) === null) {
                throw new RuntimeException('Unable to record curriculum audit.');
            }
        });
    }
}
