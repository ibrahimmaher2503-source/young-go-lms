<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Catalog\CourseStatus;
use App\Domain\Cohorts\Cohort;
use App\Domain\Settings\PlatformFlag;
use App\Domain\Shared\Search\HasSearchText;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Pennant\Feature;
use Spatie\Translatable\HasTranslations;

/**
 * @property CourseStatus $status
 * @property int $wizard_step
 * @property array<string, bool>|null $wizard_completed
 * @property list<array<string, mixed>>|null $faqs
 * @property list<array<string, mixed>>|null $requirements
 * @property list<array<string, mixed>>|null $outcomes
 * @property int|null $access_duration_days
 * @property bool $bundle_eligible
 * @property bool $manual_access_allowed
 * @property string|null $cover_image
 * @property array<string, mixed>|null $promo_video
 * @property array<string, mixed>|null $custom_values
 * @property string|null $review_reason
 * @property CarbonInterface|null $reviewed_at
 * @property string|null $overview_video_provider
 * @property string|null $overview_video_url
 * @property string|null $preview_video_provider
 * @property string|null $preview_video_url
 */
final class Course extends Model
{
    use HasSearchText;
    use HasTranslations;

    public const OWNER_ACADEMY = 'academy';

    public const OWNER_INDEPENDENT = 'independent';

    public const ACCESS_FREE = 'free';

    public const ACCESS_PAID = 'paid';

    public const ACCESS_SUBSCRIPTION = 'subscription_only';

    public const ACCESS_BOTH = 'both';

    public const INDIVIDUAL_ACCESS_MODES = [self::ACCESS_PAID, self::ACCESS_BOTH];

    public const PAID_ACCESS_MODES = [self::ACCESS_PAID, self::ACCESS_SUBSCRIPTION, self::ACCESS_BOTH];

    public const INSTRUCTOR_SCOPE_MANAGE = 'manage';

    public const SEQUENCE_FREE = 'free';

    public const SEQUENCE_SEQUENTIAL = 'sequential';

    /** @var list<string> */
    public array $translatable = ['title', 'short_description', 'description', 'seo_title', 'seo_description', 'seo_keywords'];

    protected $fillable = [
        'category_id', 'owner_instructor_id', 'title', 'short_description', 'description',
        'slug_ar', 'slug_en', 'level', 'content_language', 'min_age', 'max_age',
        'format', 'owner_type', 'status', 'access_mode', 'sequence_mode', 'featured', 'ai_enabled',
        'wizard_step', 'wizard_completed', 'faqs', 'requirements', 'outcomes',
        'overview_video_provider', 'overview_video_url', 'preview_video_provider', 'preview_video_url',
        'seo_title', 'seo_description', 'seo_keywords', 'canonical_url',
        'access_duration_days', 'bundle_eligible', 'manual_access_allowed', 'cover_image', 'promo_video', 'custom_values',
    ];

    protected $attributes = [
        'status' => 'draft',
        'format' => 'recorded',
        'owner_type' => 'academy',
        'featured' => false,
        'ai_enabled' => true,
        'wizard_step' => 1,
        'sequence_mode' => 'free',
        'bundle_eligible' => true,
        'manual_access_allowed' => true,
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
            'access_duration_days' => 'integer',
            'bundle_eligible' => 'boolean',
            'manual_access_allowed' => 'boolean',
            'promo_video' => 'array',
            'custom_values' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<Cohort, $this> */
    public function cohorts(): HasMany
    {
        return $this->hasMany(Cohort::class);
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
