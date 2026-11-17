<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Catalog\CourseStatus;
use App\Domain\Settings\PlatformFlag;
use App\Domain\Shared\Search\HasSearchText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravel\Pennant\Feature;
use Spatie\Translatable\HasTranslations;

/**
 * @property CourseStatus $status
 * @property int $wizard_step
 * @property array<string, bool>|null $wizard_completed
 * @property list<array<string, mixed>>|null $faqs
 * @property list<array<string, mixed>>|null $requirements
 * @property list<array<string, mixed>>|null $outcomes
 */
final class Course extends Model
{
    use HasSearchText;
    use HasTranslations;

    public const OWNER_ACADEMY = 'academy';

    public const OWNER_INDEPENDENT = 'independent';

    public const ACCESS_FREE = 'free';

    public const ACCESS_PAID = 'paid';

    public const INSTRUCTOR_SCOPE_MANAGE = 'manage';

    public const SEQUENCE_FREE = 'free';

    public const SEQUENCE_SEQUENTIAL = 'sequential';

    /** @var list<string> */
    public array $translatable = ['title', 'short_description', 'description'];

    protected $fillable = [
        'category_id', 'owner_instructor_id', 'title', 'short_description', 'description',
        'slug_ar', 'slug_en', 'level', 'content_language', 'min_age', 'max_age',
        'format', 'owner_type', 'status', 'access_mode', 'sequence_mode', 'featured', 'ai_enabled',
        'wizard_step', 'wizard_completed', 'faqs', 'requirements', 'outcomes',
    ];

    protected $attributes = [
        'status' => 'draft',
        'format' => 'recorded',
        'owner_type' => 'academy',
        'featured' => false,
        'ai_enabled' => true,
        'wizard_step' => 1,
        'sequence_mode' => 'free',
    ];

    protected function casts(): array
    {
        return [
            'status' => CourseStatus::class,
            'featured' => 'boolean',
            'ai_enabled' => 'boolean',
            'min_age' => 'integer',
            'max_age' => 'integer',
            'wizard_step' => 'integer',
            'wizard_completed' => 'array',
            'faqs' => 'array',
            'requirements' => 'array',
            'outcomes' => 'array',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function instructors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'course_instructors', 'course_id', 'instructor_id')
            ->withPivot('role', 'scope');
    }

    public function hasAiEnabled(): bool
    {
        return $this->ai_enabled && Feature::for(null)->active(PlatformFlag::AiAssistant->value);
    }

    protected function searchTextAttributes(): array
    {
        return ['title', 'short_description', 'description'];
    }
}
