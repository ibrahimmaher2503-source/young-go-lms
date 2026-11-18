<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\AI\Actions\IngestCurriculumDocument;
use App\Models\LessonCurriculumDocument;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Queued on upload: extracts and scores one PDF version. Safe to retry, the action ignores versions already past extraction. */
final class ProcessCurriculumDocument implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(public readonly int $documentId) {}

    public function handle(IngestCurriculumDocument $ingest): void
    {
        $document = LessonCurriculumDocument::query()->find($this->documentId);
        if ($document !== null) {
            $ingest($document);
        }
    }
}
