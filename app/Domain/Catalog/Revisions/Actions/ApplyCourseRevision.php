<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Revisions\Actions;

use App\Domain\Catalog\CourseStatus;
use App\Domain\Catalog\Revisions\RevisionDiff;
use App\Domain\Catalog\Revisions\RevisionSnapshot;
use App\Domain\Curriculum\Queries\CurriculumOutline;
use App\Domain\Media\LessonPublishRule;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\CourseRevision;
use App\Models\CourseSection;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use App\Models\Quiz;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Writes an approved revision onto the live course. Runs inside the caller's transaction, so everything lands or nothing does.
 *
 * Existing sections, lessons and items are updated in place (their ids never change, so progress, access and attempts keep
 * pointing at them); only what the revision actually changed against its starting snapshot is written, so edits made to the
 * live course in the meantime survive. Removal archives, never deletes. Nothing here reads or writes entitlements.
 */
final class ApplyCourseRevision
{
    public function __invoke(CourseRevision $revision): void
    {
        /** @var Course $course */
        $course = Course::query()->lockForUpdate()->findOrFail($revision->course_id);
        if ($course->status !== CourseStatus::Published) {
            throw ValidationException::withMessages(['course' => __('course_revisions.not_published')]);
        }

        $base = $revision->base;
        $payload = $revision->payload;
        $this->applyCourse($course, $base, $payload);
        $this->applyCurriculum($course, $base, $payload);
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $payload
     */
    private function applyCourse(Course $course, array $base, array $payload): void
    {
        $set = [];
        foreach (RevisionSnapshot::COURSE_FIELDS as $field) {
            if (! RevisionDiff::same($base['course'][$field] ?? null, $payload['course'][$field] ?? null)) {
                $set[$field] = $payload['course'][$field] ?? null;
            }
        }
        if ($set !== []) {
            $course->fill($set)->save();
        }
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $payload
     */
    private function applyCurriculum(Course $course, array $base, array $payload): void
    {
        $baseSections = RevisionSnapshot::sectionsById($base);
        $baseItems = RevisionSnapshot::itemsById($base);

        /** @var Collection<int, CourseSection> $liveSections */
        $liveSections = CourseSection::query()->where('course_id', $course->getKey())->get()->keyBy('id');
        /** @var Collection<int, CurriculumItem> $liveItems */
        $liveItems = CurriculumItem::query()->where('course_id', $course->getKey())->with('itemable')->get()->keyBy('id');

        $keptSections = [];
        $keptItems = [];
        $placed = [];      // section id => list of item ids, in the order the revision gave them
        $order = [];       // section models in revision order
        $publishCheck = [];

        foreach ((array) ($payload['sections'] ?? []) as $proposed) {
            $section = $this->section($course, $proposed, $baseSections, $liveSections);
            if ($section === null) {
                continue;
            }
            $order[] = $section;
            $keptSections[(int) $section->getKey()] = true;
            $placed[(int) $section->getKey()] = [];

            foreach ((array) $proposed['items'] as $item) {
                $model = $this->item($course, $section, $item, $baseItems, $liveItems);
                if ($model === null) {
                    continue;
                }
                $keptItems[(int) $model->getKey()] = true;
                $placed[(int) $section->getKey()][] = (int) $model->getKey();
                $before = $item['id'] === null ? null : ($baseItems[(int) $item['id']]['status'] ?? null);
                if ($item['status'] === CurriculumItem::STATUS_PUBLISHED && $before !== CurriculumItem::STATUS_PUBLISHED) {
                    $publishCheck[] = (int) $model->getKey();
                }
            }
        }

        // Anything the revision started from and no longer lists is archived (live sessions are never removed by a revision).
        foreach ($baseItems as $id => $old) {
            if (! isset($keptItems[$id]) && $old['kind'] !== 'live_session' && ($live = $liveItems->get($id)) !== null && $live->itemable instanceof Model && $live->itemable->getAttribute('archived_at') === null) {
                $live->itemable->forceFill(['archived_at' => now()])->save();
            }
        }
        foreach ($baseSections as $id => $old) {
            $live = $liveSections->get($id);
            if (! isset($keptSections[$id]) && $live !== null && $live->archived_at === null) {
                $live->update(['archived_at' => now()]);
            }
        }

        $this->reorder($course, $order, $placed, $liveSections, $keptSections, $baseSections);

        if ($publishCheck !== []) {
            LessonPublishRule::assertPublishable(CurriculumItem::query()->whereKey($publishCheck)->with(['itemable', 'course'])->get());
        }
    }

    /**
     * @param  array<string, mixed>  $proposed
     * @param  array<int, array<string, mixed>>  $baseSections
     * @param  Collection<int, CourseSection>  $live
     */
    private function section(Course $course, array $proposed, array $baseSections, $live): ?CourseSection
    {
        if ($proposed['id'] === null) {
            return CourseSection::query()->create(['course_id' => $course->getKey(), 'title' => $proposed['title'], 'sort' => 0, 'published' => (bool) $proposed['published']]);
        }

        $section = $live->get((int) $proposed['id']);
        if ($section === null || $section->archived_at !== null) {
            return null;
        }
        $old = $baseSections[(int) $proposed['id']];
        if (! RevisionDiff::same($old['title'], $proposed['title'])) {
            $section->title = $proposed['title'];
        }
        if ((bool) $old['published'] !== (bool) $proposed['published']) {
            $section->published = (bool) $proposed['published'];
        }
        $section->save();

        return $section;
    }

    /**
     * @param  array<string, mixed>  $proposed
     * @param  array<int, array<string, mixed>>  $baseItems
     * @param  Collection<int, CurriculumItem>  $live
     */
    private function item(Course $course, CourseSection $section, array $proposed, array $baseItems, $live): ?CurriculumItem
    {
        if ($proposed['id'] === null) {
            return $this->create($course, $section, $proposed);
        }

        $item = $live->get((int) $proposed['id']);
        $entity = $item?->itemable;
        if ($item === null || ! $entity instanceof Model || $entity->getAttribute('archived_at') !== null) {
            return null;
        }

        $old = $baseItems[(int) $proposed['id']];
        if ($old['kind'] !== 'live_session') {
            foreach (['title', 'description'] as $field) {
                if (! RevisionDiff::same($old[$field], $proposed[$field])) {
                    $entity->setAttribute($field, $proposed[$field]);
                }
            }
            $entity->save();
        }
        if ($old['status'] !== $proposed['status']) {
            $item->status = $proposed['status'];
        }
        $item->section_id = $section->getKey();
        $item->save();

        return $item;
    }

    /** @param array<string, mixed> $proposed */
    private function create(Course $course, CourseSection $section, array $proposed): CurriculumItem
    {
        $entity = match ($proposed['kind']) {
            'quiz' => Quiz::query()->create(['title' => $proposed['title'], 'description' => $proposed['description']]),
            'assignment' => Assignment::query()->create(['title' => $proposed['title'], 'description' => $proposed['description']]),
            default => Lesson::query()->create(['type' => $proposed['lesson_type'] ?? 'text', 'title' => $proposed['title'], 'description' => $proposed['description']]),
        };

        return CurriculumItem::query()->create([
            'course_id' => $course->getKey(), 'section_id' => $section->getKey(), 'sort' => 0,
            'itemable_type' => (string) $proposed['kind'], 'itemable_id' => $entity->getKey(), 'status' => $proposed['status'],
            'association' => $proposed['kind'] === 'lesson' ? null : 'section',
        ]);
    }

    /**
     * Final order: the revision's order first, then anything added to the live course after the revision started.
     *
     * @param  list<CourseSection>  $order
     * @param  array<int, list<int>>  $placed
     * @param  Collection<int, CourseSection>  $liveSections
     * @param  array<int, bool>  $keptSections
     * @param  array<int, array<string, mixed>>  $baseSections
     */
    private function reorder(Course $course, array $order, array $placed, $liveSections, array $keptSections, array $baseSections): void
    {
        $sort = 0;
        foreach ($order as $section) {
            $section->update(['sort' => ++$sort]);
        }
        foreach ($liveSections->sortBy('sort') as $extra) {
            if ($extra->archived_at === null && ! isset($keptSections[(int) $extra->getKey()]) && ! isset($baseSections[(int) $extra->getKey()])) {
                $extra->update(['sort' => ++$sort]);
            }
        }

        foreach ($placed as $sectionId => $ids) {
            $extras = CurriculumOutline::activeItems(CurriculumItem::query()->where('section_id', $sectionId)->whereNotIn('id', $ids))->orderBy('sort')->orderBy('id')->pluck('id')->map(static fn ($id): int => (int) $id)->all();
            foreach ([...$ids, ...$extras] as $position => $id) {
                DB::table('curriculum_items')->where('id', $id)->update(['sort' => $position + 1, 'section_id' => $sectionId]);
            }
        }
    }
}
