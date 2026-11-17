<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A learner file inside one submission revision. Private; the stored path is never sent to a browser.
 *
 * @property int $id
 * @property int $revision_id
 * @property string $kind
 * @property string $display_name
 * @property string $path
 * @property string $mime
 * @property int $size
 */
final class SubmissionFile extends Model
{
    protected $guarded = ['id'];

    /** @var list<string> */
    protected $hidden = ['path'];

    /** @return BelongsTo<SubmissionRevision, $this> */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(SubmissionRevision::class, 'revision_id');
    }
}
