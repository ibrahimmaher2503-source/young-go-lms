<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;

/**
 * @property Carbon|null $approved_at
 */
final class InstructorProfile extends Model
{
    use HasTranslations;

    public const APPROVED = 'approved';

    public const DRAFT = 'draft';

    public const HIDDEN = 'hidden';

    /** @var list<string> */
    public array $translatable = ['display_name', 'headline', 'bio', 'seo_title', 'seo_description'];

    protected $fillable = ['user_id', 'slug_ar', 'slug_en', 'display_name', 'headline', 'bio', 'avatar', 'seo_title', 'seo_description', 'status'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsToMany<Category, $this> */
    public function disciplines(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'instructor_profile_category', 'instructor_profile_id', 'category_id');
    }

    /** Public name: the profile's own display name, then the account display name, then the account name (never email or username). */
    public function publicName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return trim((string) ($this->getTranslation('display_name', $locale) ?: $this->user?->getAttribute('display_name') ?: $this->user?->name));
    }
}
