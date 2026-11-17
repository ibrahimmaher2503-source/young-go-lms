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

final class Course extends Model
{
    use HasSearchText;
    use HasTranslations;

    public const OWNER_ACADEMY = 'academy';

    public const OWNER_INDEPENDENT = 'independent';

    public const ACCESS_FREE = 'free';

    public const ACCESS_PAID = 'paid';

    public const INSTRUCTOR_SCOPE_MANAGE = 'manage';

    /** @var list<string> */
    public array $translatable = ['title', 'short_description', 'description'];

    protected $fillable = [
        'category_id', 'owner_instructor_id', 'title', 'short_description', 'description',
        'slug_ar', 'slug_en', 'level', 'content_language', 'min_age', 'max_age',
        'format', 'owner_type', 'status', 'access_mode', 'featured', 'ai_enabled',
    ];

    protected $attributes = [
        'status' => 'draft',
        'format' => 'recorded',
        'owner_type' => 'academy',
        'featured' => false,
        'ai_enabled' => true,
    ];

    protected function casts(): array
    {
        return [
            'status' => CourseStatus::class,
            'featured' => 'boolean',
            'ai_enabled' => 'boolean',
            'min_age' => 'integer',
            'max_age' => 'integer',
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
