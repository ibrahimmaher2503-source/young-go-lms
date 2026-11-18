<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * An admin-defined extra field on every course (PRD 9.2 step 6). `public` fields are shown on the public course page;
 * internal ones stay in the staff wizard. Values live in `courses.custom_values`, keyed by `field_key`.
 *
 * @property int $id
 * @property string $field_key
 * @property string $type
 * @property list<string>|null $options
 * @property bool $is_required
 * @property int|null $min_value
 * @property int|null $max_value
 * @property string $visibility
 * @property bool $is_translatable
 * @property bool $is_active
 * @property int $sort
 */
final class CourseCustomField extends Model
{
    use HasTranslations;

    public const TYPE_TEXT = 'text';

    public const TYPE_TEXTAREA = 'textarea';

    public const TYPE_NUMBER = 'number';

    public const TYPE_SELECT = 'select';

    public const TYPE_TOGGLE = 'toggle';

    public const TYPE_URL = 'url';

    public const TYPE_DATE = 'date';

    public const TYPES = [self::TYPE_TEXT, self::TYPE_TEXTAREA, self::TYPE_NUMBER, self::TYPE_SELECT, self::TYPE_TOGGLE, self::TYPE_URL, self::TYPE_DATE];

    public const VISIBILITY_PUBLIC = 'public';

    public const VISIBILITY_INTERNAL = 'internal';

    /** @var list<string> */
    public array $translatable = ['label'];

    protected $table = 'course_custom_fields';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'options' => 'array', 'is_required' => 'boolean', 'is_translatable' => 'boolean', 'is_active' => 'boolean',
            'min_value' => 'integer', 'max_value' => 'integer', 'sort' => 'integer',
        ];
    }

    /** Whether the value is stored once per language (text and textarea only). */
    public function perLocale(): bool
    {
        return $this->is_translatable && in_array($this->type, [self::TYPE_TEXT, self::TYPE_TEXTAREA], true);
    }
}
