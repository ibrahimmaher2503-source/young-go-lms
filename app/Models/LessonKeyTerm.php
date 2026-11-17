<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A term taken from the approved text for the assistant's starter chips. Shown to learners only once approved.
 *
 * @property int $document_id
 * @property string $term
 * @property string $locale
 * @property bool $approved
 */
final class LessonKeyTerm extends Model
{
    protected $fillable = ['document_id', 'term', 'locale', 'approved'];

    protected function casts(): array
    {
        return ['approved' => 'boolean'];
    }
}
