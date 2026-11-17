<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One page of a curriculum PDF. raw_text is what extraction produced; approved_text is what a person signed off and
 * is the only text the assistant may read.
 *
 * @property int $document_id
 * @property int $page_no
 * @property string|null $raw_text
 * @property string|null $approved_text
 * @property bool $ocr_used
 * @property string|null $quality_score
 */
final class LessonDocumentPage extends Model
{
    protected $fillable = ['document_id', 'page_no', 'raw_text', 'approved_text', 'ocr_used', 'quality_score'];

    protected function casts(): array
    {
        return ['page_no' => 'integer', 'ocr_used' => 'boolean'];
    }
}
