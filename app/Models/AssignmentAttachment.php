<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

/**
 * A file the instructor attaches to an assignment brief. Private: opened only through signed links.
 *
 * @property int $id
 * @property int $assignment_id
 * @property string $path
 * @property string $mime
 * @property int $size
 */
final class AssignmentAttachment extends Model
{
    use HasTranslations;

    /** @var list<string> */
    public array $translatable = ['title'];

    protected $guarded = ['id'];

    /** @var list<string> */
    protected $hidden = ['path'];

    /** @return BelongsTo<Assignment, $this> */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }
}
