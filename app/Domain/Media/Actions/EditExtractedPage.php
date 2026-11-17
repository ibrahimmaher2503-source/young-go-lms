<?php

declare(strict_types=1);

namespace App\Domain\Media\Actions;

use App\Domain\Audit\AuditLogger;
use App\Models\LessonCurriculumDocument;
use App\Models\LessonDocumentPage;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Lets an approver fix extracted text (for example OCR mistakes) before approving. Every edit is audited. */
final class EditExtractedPage
{
    public const int MAX_CHARACTERS = 20000;

    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $actor, LessonCurriculumDocument $document, int $pageNo, string $text): LessonDocumentPage
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('lesson_document.approve')) {
            throw new AuthorizationException;
        }

        $text = trim((string) preg_replace('/[^\P{Cc}\n\t]/u', '', str_replace(["\r\n", "\r"], "\n", $text)));
        if ($text === '' || mb_strlen($text) > self::MAX_CHARACTERS) {
            throw ValidationException::withMessages(['text' => __('media.pdf_text_invalid', ['max' => self::MAX_CHARACTERS])]);
        }

        return DB::transaction(function () use ($actor, $document, $pageNo, $text): LessonDocumentPage {
            $current = LessonCurriculumDocument::query()->lockForUpdate()->findOrFail($document->getKey());
            if ($current->status !== LessonCurriculumDocument::STATUS_AWAITING_APPROVAL) {
                throw ValidationException::withMessages(['document' => __('media.pdf_not_ready_for_approval')]);
            }

            $page = LessonDocumentPage::query()->where('document_id', $current->getKey())->where('page_no', $pageNo)->firstOrFail();
            $before = (string) ($page->approved_text ?? $page->raw_text);
            $page->forceFill(['approved_text' => $text])->save();

            if ($this->audit->record('curriculum_document.text_edited', $current->lesson, (int) $actor->getKey(), ['sha1' => sha1($before), 'length' => mb_strlen($before)], ['sha1' => sha1($text), 'length' => mb_strlen($text)], ['version' => $current->version, 'page' => $pageNo]) === null) {
                throw new RuntimeException('Unable to record curriculum document audit.');
            }

            return $page;
        });
    }
}
