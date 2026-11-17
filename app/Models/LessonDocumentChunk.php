<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A slice of approved text, kept only for chunked mode (documents above the full-context threshold).
 *
 * @property int $document_id
 * @property int $page_from
 * @property int $page_to
 * @property string $text
 * @property string|null $embedding
 */
final class LessonDocumentChunk extends Model
{
    protected $fillable = ['document_id', 'page_from', 'page_to', 'text', 'embedding'];
}
