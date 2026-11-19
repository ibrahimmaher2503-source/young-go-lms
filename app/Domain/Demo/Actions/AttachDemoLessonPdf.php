<?php

declare(strict_types=1);

namespace App\Domain\Demo\Actions;

use App\Domain\AI\Providers\FakeAiProvider;
use App\Domain\Demo\DemoAnchors;
use App\Domain\Demo\DemoGuard;
use App\Domain\Media\Actions\ApproveCurriculumDocument;
use App\Domain\Media\Actions\ApproveKeyTerms;
use App\Models\Lesson;
use App\Models\LessonCurriculumDocument;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/** Attaches the checked, single-page demo fixture without invoking OCR or an external AI provider. */
final class AttachDemoLessonPdf
{
    public const string DIRECTORY = 'demo/curriculum';

    public function __invoke(User $actor, Lesson $lesson): void
    {
        DemoGuard::assertAllowed();
        if (! in_array($lesson->item?->course_id, DemoAnchors::ids('courses'), true)) {
            throw new RuntimeException('The demo PDF requires a demo lesson.');
        }
        $bytes = file_get_contents(database_path('seeders/fixtures/demo-perspective.pdf'));
        $disk = Storage::disk(Config::string('media.disk'));
        $path = self::DIRECTORY.'/'.Str::uuid().'.pdf';
        if ($bytes === false || ! $disk->put($path, $bytes)) {
            throw new RuntimeException('Unable to store the demo curriculum PDF.');
        }

        try {
            $document = $lesson->curriculumDocuments()->create([
                'version' => 1, 'path' => $path, 'sha256' => hash('sha256', $bytes), 'size' => strlen($bytes),
                'pages' => 1, 'status' => LessonCurriculumDocument::STATUS_AWAITING_APPROVAL, 'uploaded_by' => $actor->id,
            ]);
            $document->documentPages()->create([
                'page_no' => 1, 'ocr_used' => false,
                'raw_text' => <<<'TEXT'
                    Perspective drawing
                    A vanishing point is where parallel lines appear to meet on the horizon.
                    Draw a horizon line, mark the vanishing point, and sketch parallel rails.
                    Repeat the perspective exercise with a road instead of rails.
                    TEXT,
            ]);
            $approve = app()->makeWith(ApproveCurriculumDocument::class, ['ai' => new FakeAiProvider]);
            $document = $approve($actor, $document);
            app(ApproveKeyTerms::class)($actor, $document, $document->keyTerms()->pluck('id')->all());
        } catch (Throwable $exception) {
            $disk->delete($path);

            throw $exception;
        }
    }
}
