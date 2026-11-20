<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Catalog\CourseStatus;
use App\Jobs\ProcessCurriculumDocument;
use App\Models\Assignment;
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
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Makes an independent draft copy of a course: sections, lessons (with their text, media, materials and captions), quizzes
 * and assignments in the same order. Files are copied, never shared, so deleting or replacing one course's file never
 * touches the other. Curriculum PDFs are copied as new uploads that have to be read and approved again before the
 * assistant can use them. Access, progress and orders are never copied.
 */
final class DuplicateCourse
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $actor, Course $source): Course
    {
        Gate::forUser($actor)->authorize('duplicate', $source);

        $copied = [];
        $documents = [];

        try {
            $copy = DB::transaction(function () use ($actor, $source, &$copied, &$documents): Course {
                $copy = $this->copyCourse($source);
                $sections = [];
                foreach (CourseSection::query()->where('course_id', $source->getKey())->whereNull('archived_at')->orderBy('sort')->orderBy('id')->get() as $section) {
                    $sections[$section->getKey()] = CourseSection::query()->create([
                        'course_id' => $copy->getKey(), 'title' => $section->getTranslations('title'), 'sort' => $section->sort, 'published' => false,
                    ]);
                }

                $items = CurriculumItem::query()->where('course_id', $source->getKey())->whereIn('section_id', array_keys($sections))->orderBy('section_id')->orderBy('sort')->orderBy('id')->with('itemable')->get();
                $itemCopies = [];
                foreach ($items as $item) {
                    $entity = $item->itemable;
                    if ($entity === null || $entity->getAttribute('archived_at') !== null) {
                        continue;
                    }

                    $newEntity = match (true) {
                        $entity instanceof Lesson => $this->copyLesson($entity, $copied, $documents),
                        $entity instanceof Quiz, $entity instanceof Assignment => $entity::query()->create(['title' => $entity->getTranslations('title'), 'description' => $entity->getTranslations('description')]),
                        default => null,
                    };
                    if ($newEntity === null) {
                        continue;
                    }

                    $itemCopies[$item->getKey()] = CurriculumItem::query()->create([
                        'course_id' => $copy->getKey(), 'section_id' => $sections[$item->section_id]->getKey(), 'sort' => $item->sort,
                        'itemable_type' => $item->itemable_type, 'itemable_id' => $newEntity->getKey(), 'status' => CurriculumItem::STATUS_DRAFT,
                        'visibility' => $item->visibility, 'unlock_rule' => $item->unlock_rule, 'unlock_at' => $item->unlock_at, 'unlock_days' => $item->unlock_days,
                        'required' => $item->required, 'ai_enabled' => $item->ai_enabled, 'association' => $item->association,
                    ]);
                }

                foreach ($items as $item) {
                    $lessonId = $item->associated_lesson_item_id;
                    if ($lessonId !== null && isset($itemCopies[$item->getKey()], $itemCopies[$lessonId])) {
                        $itemCopies[$item->getKey()]->update(['associated_lesson_item_id' => $itemCopies[$lessonId]->getKey()]);
                    }
                }

                $copy->instructors()->sync($source->instructors->mapWithKeys(function (User $u): array {
                    $pivot = $u->getRelation('pivot');

                    return [$u->getKey() => ['role' => $pivot->getAttribute('role'), 'scope' => $pivot->getAttribute('scope')]];
                })->all());

                if ($this->audit->record('course.duplicated', $copy, (int) $actor->getKey(), null, ['source_course_id' => $source->getKey()]) === null) {
                    throw new RuntimeException('Unable to record course audit.');
                }

                return $copy;
            });
        } catch (Throwable $exception) {
            Storage::disk(Config::string('media.disk'))->delete($copied);
            throw $exception;
        }

        if (Config::boolean('ai.ingestion.dispatch_on_upload')) {
            foreach ($documents as $id) {
                ProcessCurriculumDocument::dispatch($id);
            }
        }

        return $copy;
    }

    private function copyCourse(Course $source): Course
    {
        $suffix = ['ar' => ' (نسخة)', 'en' => ' (copy)'];
        $title = [];
        foreach ($source->getTranslations('title') as $locale => $text) {
            $title[$locale] = $text.($suffix[$locale] ?? ' (copy)');
        }

        return Course::query()->create([
            'category_id' => $source->category_id, 'owner_instructor_id' => $source->owner_instructor_id, 'title' => $title,
            'short_description' => $source->getTranslations('short_description'), 'description' => $source->getTranslations('description'),
            'slug_ar' => $this->uniqueSlug('slug_ar', (string) $source->slug_ar), 'slug_en' => $this->uniqueSlug('slug_en', (string) $source->slug_en),
            'level' => $source->level, 'content_language' => $source->content_language, 'min_age' => $source->min_age, 'max_age' => $source->max_age,
            'format' => $source->format, 'owner_type' => $source->owner_type, 'status' => CourseStatus::Draft, 'access_mode' => $source->access_mode,
            'featured' => false, 'ai_enabled' => $source->ai_enabled, 'wizard_step' => 1, 'wizard_completed' => [],
            'faqs' => $source->faqs, 'requirements' => $source->requirements, 'outcomes' => $source->outcomes,
        ]);
    }

    /** @param array<string, mixed> $node
     * @param  array<int, int>  $resourceIds
     * @return array<string, mixed>|null
     */
    private function remapImages(array $node, array $resourceIds): ?array
    {
        if (($node['type'] ?? null) === 'image') {
            $id = $resourceIds[(int) ($node['attrs']['resource_id'] ?? 0)] ?? null;
            if ($id === null) {
                return null;
            }
            $node['attrs']['resource_id'] = $id;
        }
        if (is_array($node['content'] ?? null)) {
            $children = [];
            foreach ($node['content'] as $child) {
                if (is_array($child) && ($mapped = $this->remapImages($child, $resourceIds)) !== null) {
                    $children[] = $mapped;
                }
            }
            $node['content'] = $children;
        }

        return $node;
    }

    private function uniqueSlug(string $column, string $base): string
    {
        $base = trim($base, '-');
        $base = $base === '' ? 'course' : $base;
        $suffix = '-copy';
        $slug = (rtrim(mb_substr($base, 0, 120 - strlen($suffix)), '-') ?: 'course').$suffix;
        for ($i = 2; Course::query()->where($column, $slug)->exists(); $i++) {
            $suffix = '-copy-'.$i;
            $slug = (rtrim(mb_substr($base, 0, 120 - strlen($suffix)), '-') ?: 'course').$suffix;
        }

        return $slug;
    }

    /**
     * @param  list<string>  $copied  every file written, so a failure can remove them
     * @param  list<int>  $documents  ids of curriculum PDFs that need reading again
     */
    private function copyLesson(Lesson $lesson, array &$copied, array &$documents): Lesson
    {
        $new = Lesson::query()->create([
            'type' => $lesson->type, 'title' => $lesson->getTranslations('title'), 'description' => $lesson->getTranslations('description'),
            'duration_seconds' => $lesson->duration_seconds, 'is_preview' => $lesson->is_preview,
        ]);

        $media = MediaAsset::query()->where('lesson_id', $lesson->getKey())->first();
        if ($media !== null) {
            $attributes = $media->only(['provider', 'kind', 'external_id', 'url', 'protection_tier', 'exception_reason', 'exception_by', 'exception_at', 'duration_seconds', 'status', 'metadata']);
            $attributes['library_item_id'] = $media->library_item_id;
            $attributes['path'] = $media->library_item_id === null ? $this->copyFile($media->path, 'media', $copied) : $media->path;
            MediaAsset::query()->create(['lesson_id' => $new->getKey()] + $attributes);
        }

        $resourceIds = [];
        foreach (LessonResource::query()->where('lesson_id', $lesson->getKey())->get() as $resource) {
            $attributes = $resource->only(['type', 'url', 'mime', 'size', 'sha256', 'downloadable', 'visibility', 'is_preview', 'sort', 'uploaded_by', 'library_item_id']);
            $path = $resource->library_item_id === null ? $this->copyFile($resource->path, 'resources', $copied) : $resource->path; // library files are shared by reference
            $copy = LessonResource::query()->create(['lesson_id' => $new->getKey(), 'title' => $resource->getTranslations('title'), 'path' => $path] + $attributes);
            if ($resource->type === 'image') {
                $resourceIds[$resource->getKey()] = $copy->getKey();
            }
        }
        if (is_array($lesson->content)) {
            $new->update(['content' => $this->remapImages($lesson->content, $resourceIds)]);
        }

        foreach (SubtitleTrack::query()->where('lesson_id', $lesson->getKey())->get() as $track) {
            SubtitleTrack::query()->create(['lesson_id' => $new->getKey(), 'path' => (string) $this->copyFile($track->path, 'subtitles', $copied)] + $track->only(['locale', 'label', 'sha256', 'size', 'is_default', 'uploaded_by']));
        }

        $document = LessonCurriculumDocument::query()->where('lesson_id', $lesson->getKey())->orderByDesc('active')->orderByDesc('version')->first();
        if ($document !== null) {
            $copy = LessonCurriculumDocument::query()->create([
                'lesson_id' => $new->getKey(), 'version' => 1, 'path' => (string) $this->copyFile($document->path, 'documents', $copied),
                'sha256' => $document->sha256, 'size' => $document->size, 'pages' => $document->pages,
                'status' => LessonCurriculumDocument::STATUS_UPLOADED, 'active' => false, 'uploaded_by' => $document->uploaded_by,
            ]);
            $documents[] = (int) $copy->getKey();
        }

        return $new;
    }

    /** @param  list<string>  $copied */
    private function copyFile(?string $path, string $directory, array &$copied): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $disk = Storage::disk(Config::string('media.disk'));
        if (! $disk->exists($path)) {
            return $path; // the source file is already missing; keep the reference rather than invent a file
        }

        $target = $directory.'/'.Str::uuid()->toString().'.'.pathinfo($path, PATHINFO_EXTENSION);
        $disk->copy($path, $target);
        $copied[] = $target;

        return $target;
    }
}
