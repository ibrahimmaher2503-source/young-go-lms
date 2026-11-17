<?php

declare(strict_types=1);

namespace App\Domain\Media\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Curriculum\CurriculumAccess;
use App\Domain\Media\PdfInspector;
use App\Jobs\ProcessCurriculumDocument;
use App\Models\Lesson;
use App\Models\LessonCurriculumDocument;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\File;
use Throwable;

/**
 * Stores a lesson's curriculum PDF as a new version. Validation follows AI spec 6.1: real PDF content, size and page
 * limits, not encrypted, no active content, SHA-256 recorded. The same file twice is a no-op. A new version never
 * replaces the active one: that only happens when it is approved (ApproveCurriculumDocument).
 */
final class UploadCurriculumDocument
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $actor, Lesson $lesson, File $file): LessonCurriculumDocument
    {
        $item = $lesson->item()->with('course')->first();
        abort_if($item === null, 404);
        CurriculumAccess::authorize($actor, $item->course);

        $fail = static fn (string $key, array $replace = []) => ValidationException::withMessages(['file' => __($key, $replace)]);

        $size = (int) $file->getSize();
        if ($size <= 0 || $size > Config::integer('media.pdf_max_kilobytes') * 1024) {
            throw $fail('media.pdf_too_large', ['mb' => (int) round(Config::integer('media.pdf_max_kilobytes') / 1024)]);
        }
        if ($file->getMimeType() !== 'application/pdf') {
            throw $fail('media.pdf_invalid');
        }

        $report = PdfInspector::inspect($file->getPathname());
        if (! $report['valid'] || $report['pages'] < 1) {
            throw $fail('media.pdf_invalid');
        }
        if ($report['encrypted']) {
            throw $fail('media.pdf_encrypted');
        }
        if ($report['active_content']) {
            throw $fail('media.pdf_active_content');
        }
        if ($report['pages'] > Config::integer('media.pdf_max_pages')) {
            throw $fail('media.pdf_too_many_pages', ['max' => Config::integer('media.pdf_max_pages')]);
        }

        $hash = hash_file('sha256', $file->getPathname());
        $existing = LessonCurriculumDocument::query()->where('lesson_id', $lesson->getKey())->where('sha256', $hash)->first();
        if ($existing !== null) {
            return $existing; // same bytes again: nothing to store, nothing to version
        }

        $disk = Storage::disk(Config::string('media.disk'));
        $path = 'documents/'.Str::uuid()->toString().'.pdf';
        $stream = fopen($file->getPathname(), 'rb');
        if ($stream === false || ! $disk->put($path, $stream)) {
            throw $fail('media.store_failed');
        }
        fclose($stream);

        try {
            $document = DB::transaction(function () use ($actor, $lesson, $path, $hash, $size, $report): LessonCurriculumDocument {
                Lesson::query()->whereKey($lesson->getKey())->lockForUpdate()->first();
                $version = (int) LessonCurriculumDocument::query()->where('lesson_id', $lesson->getKey())->max('version') + 1;
                $document = LessonCurriculumDocument::query()->create([
                    'lesson_id' => $lesson->getKey(), 'version' => $version, 'path' => $path, 'sha256' => $hash, 'size' => $size,
                    'pages' => $report['pages'], 'status' => LessonCurriculumDocument::STATUS_UPLOADED, 'active' => false, 'uploaded_by' => $actor->getKey(),
                ]);

                if ($this->audit->record('curriculum_document.uploaded', $lesson, (int) $actor->getKey(), null, ['version' => $version, 'pages' => $report['pages']]) === null) {
                    throw new RuntimeException('Unable to record curriculum document audit.');
                }

                return $document;
            });
        } catch (Throwable $exception) {
            $disk->delete($path);
            throw $exception;
        }

        if (Config::boolean('ai.ingestion.dispatch_on_upload')) {
            ProcessCurriculumDocument::dispatch($document->getKey());
        }

        return $document;
    }
}
