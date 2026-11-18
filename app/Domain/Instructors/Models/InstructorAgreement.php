<?php

declare(strict_types=1);

namespace App\Domain\Instructors\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;

/**
 * One version of the instructor agreement (YG-GAP-INS-002). A published version is immutable: changes need a new version.
 * The legal wording is an owner/counsel decision (OPEN); seeded text is a draft placeholder.
 *
 * @property int $id
 * @property string $version
 * @property string $status
 * @property Carbon|null $published_at
 * @property int|null $published_by
 */
final class InstructorAgreement extends Model
{
    use HasTranslations;

    public const string DRAFT = 'draft';

    public const string PUBLISHED = 'published';

    /** @var list<string> */
    public array $translatable = ['title', 'body'];

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function isPublished(): bool
    {
        return $this->status === self::PUBLISHED;
    }
}
