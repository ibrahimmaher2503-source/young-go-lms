<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Catalog\CourseCustomValues;
use App\Domain\Catalog\CourseDescription;
use App\Domain\Catalog\CourseMedia;
use App\Domain\Catalog\CourseStatus;
use App\Domain\Catalog\Revisions\RevisionAccess;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Content\RichContent;
use App\Domain\Media\LessonPublishRule;
use App\Domain\Shared\Markets\Market;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\User;
use Brick\Money\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;

/**
 * A draft may be saved at any point with
 * incomplete data ($complete = false); passing a step ($complete = true) enforces its required fields.
 * Ownership, status and featured are changed only by users who hold the matching permission.
 */
final class SaveCourseWizard
{
    public const int LAST_STEP = 7;

    public const array LEVELS = ['beginner', 'intermediate', 'advanced', 'all'];

    public const array FORMATS = ['recorded', 'live', 'hybrid'];

    public const array CONTENT_LANGUAGES = ['ar', 'en', 'both'];

    /** Statuses the wizard may set; publishing and review go through their own actions. */
    public const array WIZARD_STATUSES = ['draft', 'private', 'upcoming', 'suspended', 'archived'];

    private const array OWNERSHIP_FIELDS = ['owner_type', 'owner_instructor_id'];

    private const array STATUS_FIELDS = ['status', 'featured'];

    private const int MAX_ROWS = 30;

    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function __invoke(User $actor, array $data, ?Course $course = null, int $step = 1, bool $complete = false): Course
    {
        if ($course === null) {
            Gate::forUser($actor)->authorize('create', Course::class);
        } else {
            Gate::forUser($actor)->authorize('update', $course);
            // T-P3-07: a published independent course is changed through a revision, not here.
            RevisionAccess::assertLiveEditable($actor, $course);
        }

        $data = $this->guardPrivileged($actor, $course, $data);
        $pricing = $this->validatePricing($actor, $data, $step === 3 && $complete);
        $clean = $this->validate($data, $course, $complete ? $step : null);
        if ($course?->status === CourseStatus::PendingReview && ! $actor->can('course.publish')) {
            throw ValidationException::withMessages(['status' => __('course_wizard.review.locked_pending')]);
        }
        // T-P3-03: custom values, promo video and cover validate before anything is written.
        $custom = array_key_exists('custom', $data) ? CourseCustomValues::validated((array) $data['custom'], $course?->custom_values, $step === 6 && $complete) : null;
        $promo = array_key_exists('promo_video', $data) ? ['value' => CourseMedia::promoVideo((array) $data['promo_video'])] : null;
        $cover = $this->coverChange($data, $course);
        $newCover = $cover['file'] === null ? null : CourseMedia::storeCover($cover['file']);

        try {
            $saved = $this->persist($actor, $clean, $course, $step, $complete, $pricing, $custom, $promo, $newCover, $cover['remove']);
        } catch (Throwable $exception) {
            CourseMedia::deleteCover($newCover);

            throw $exception;
        }

        $oldCover = $course?->cover_image;
        if (($newCover !== null || $cover['remove']) && $oldCover !== null && $oldCover !== $saved->cover_image) {
            CourseMedia::deleteCover($oldCover);
        }

        return $saved;
    }

    /**
     * @param  array{attributes: array<string, mixed>, slugs: array<string, string>}  $clean
     * @param  array<string, mixed>  $pricing
     * @param  array<string, mixed>|null  $custom
     * @param  array{value: array<string, mixed>|null}|null  $promo
     */
    private function persist(User $actor, array $clean, ?Course $course, int $step, bool $complete, array $pricing, ?array $custom, ?array $promo, ?string $newCover, bool $removeCover): Course
    {
        return DB::transaction(function () use ($actor, $clean, $course, $step, $complete, $pricing, $custom, $promo, $newCover, $removeCover): Course {
            $record = $course === null ? new Course : Course::query()->lockForUpdate()->findOrFail($course->getKey());
            if ($custom !== null) {
                $record->custom_values = $custom;
            }
            if ($promo !== null) {
                $record->promo_video = $promo['value'];
            }
            if ($newCover !== null) {
                $record->cover_image = $newCover;
            } elseif ($removeCover) {
                $record->cover_image = null;
            }
            $before = $record->exists ? Arr::only($record->getAttributes(), ['owner_type', 'owner_instructor_id', 'status', 'featured']) : null;
            $record->fill($clean['attributes']);
            $record->slug_ar = $clean['slugs']['slug_ar'] ?? $record->slug_ar ?? $this->uniqueSlug('slug_ar', $record, $record->getTranslation('title', 'ar', false) ?: $record->getTranslation('title', 'en', false), 'ar');
            $record->slug_en = $clean['slugs']['slug_en'] ?? $record->slug_en ?? $this->uniqueSlug('slug_en', $record, $record->getTranslation('title', 'en', false) ?: $record->getTranslation('title', 'ar', false), 'en');

            $completed = is_array($record->wizard_completed) ? $record->wizard_completed : [];
            if ($complete) {
                $completed[(string) $step] = true;
            }
            $record->wizard_completed = $completed;
            $record->wizard_step = min(self::LAST_STEP, max((int) $record->wizard_step, $complete ? $step + 1 : $step));
            $created = ! $record->exists;
            // T-P3-07 (YG-GAP-CHG-002): direct edits to a live course are audited with before and after.
            $liveChanges = $record->exists && $record->getOriginal('status') === CourseStatus::Published
                ? Arr::except($record->getDirty(), ['wizard_step', 'wizard_completed', 'updated_at', 'search_text']) : [];
            $liveBefore = Arr::only($record->getOriginal(), array_keys($liveChanges));
            $instructorCreator = $created && ! $actor->can('panel.admin.access');
            if ($instructorCreator && $actor->hasRole('instructor_independent')) {
                $record->owner_type = Course::OWNER_INDEPENDENT;
                $record->owner_instructor_id = $actor->getKey();
            }
            if ($record->getTranslations('title') === []) {
                $record->setTranslations('title', []);
            }
            $record->save();
            if ($instructorCreator) {
                $record->instructors()->attach($actor, [
                    'role' => $record->owner_type === Course::OWNER_INDEPENDENT ? 'owner' : 'instructor',
                    'scope' => Course::INSTRUCTOR_SCOPE_MANAGE,
                ]);
            }

            $this->auditChange($actor, $record, $created, $before);
            if ($liveChanges !== [] && $this->audit->record('course.updated_live', $record, (int) $actor->getKey(), $liveBefore, $liveChanges) === null) {
                throw new RuntimeException('Unable to record course audit.');
            }
            if ($pricing !== []) {
                $this->savePricing($actor, $record, $pricing);
            }

            return $record;
        });
    }

    public static function canSetPricing(User $actor): bool
    {
        return $actor->can('panel.admin.access') && $actor->can('course.publish') && ! $actor->hasRole('instructor_independent');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{access_mode?: string|null, market_prices?: array<string, string|null>, access_duration_days?: int|string|null, bundle_eligible?: bool|null, manual_access_allowed?: bool|null}
     */
    private function validatePricing(User $actor, array $data, bool $complete): array
    {
        $fields = Arr::only($data, ['access_mode', 'market_prices', 'access_duration_days', 'bundle_eligible', 'manual_access_allowed']);
        if (! self::canSetPricing($actor)) {
            if ($fields !== []) {
                throw new AuthorizationException;
            }

            return [];
        }

        $individual = in_array($fields['access_mode'] ?? null, Course::INDIVIDUAL_ACCESS_MODES, true);
        $rules = [
            'access_mode' => [$complete ? 'required' : 'nullable', Rule::in([Course::ACCESS_FREE, ...Course::PAID_ACCESS_MODES])],
            'market_prices' => ['nullable', 'array:'.implode(',', array_column(Market::cases(), 'value'))],
            // Empty means lifetime access; the duration is applied to free, purchase and bundle grants (GrantEntitlement).
            'access_duration_days' => ['nullable', 'integer', 'between:1,3650'],
            'bundle_eligible' => ['nullable', 'boolean'],
            'manual_access_allowed' => ['nullable', 'boolean'],
        ];
        foreach (Market::cases() as $market) {
            $rules['market_prices.'.$market->value] = [
                $individual && $complete && $market->isActive() ? 'required' : 'nullable',
                'regex:/^\d{1,9}(?:\.\d{1,2})?$/D', 'numeric', 'gt:0',
            ];
        }

        return Validator::make($fields, $rules)->validate();
    }

    /** @param array{access_mode?: string|null, market_prices?: array<string, string|null>, access_duration_days?: int|string|null, bundle_eligible?: bool|null, manual_access_allowed?: bool|null} $pricing */
    private function savePricing(User $actor, Course $course, array $pricing): void
    {
        $mode = $pricing['access_mode'] ?? $course->access_mode;
        $before = ['access_mode' => $course->access_mode, 'market_prices' => []];
        $after = ['access_mode' => $mode, 'market_prices' => []];

        $settings = [];
        if (array_key_exists('access_duration_days', $pricing)) {
            $settings['access_duration_days'] = blank($pricing['access_duration_days']) ? null : (int) $pricing['access_duration_days'];
        }
        foreach (['bundle_eligible', 'manual_access_allowed'] as $flag) {
            if (array_key_exists($flag, $pricing) && $pricing[$flag] !== null) {
                $settings[$flag] = (bool) $pricing[$flag];
            }
        }
        foreach ($settings as $name => $value) {
            if ($course->getAttribute($name) !== $value) {
                $before[$name] = $course->getAttribute($name);
                $after[$name] = $value;
            }
        }
        if ($settings !== []) {
            $course->forceFill($settings)->save();
        }
        if ($mode !== $course->access_mode) {
            if (in_array($mode, Course::PAID_ACCESS_MODES, true)) {
                $items = CurriculumItem::query()->where('course_id', $course->getKey())
                    ->where('status', CurriculumItem::STATUS_PUBLISHED)->where('itemable_type', 'lesson')->with('itemable.media')->get();
                $course->access_mode = $mode;
                foreach ($items as $item) {
                    $item->setRelation('course', $course);
                }
                if (LessonPublishRule::blocked($items) !== []) {
                    throw ValidationException::withMessages(['access_mode' => __('courses.pricing_protected_media')]);
                }
            }
            $course->access_mode = $mode;
            $course->save();
        }

        if (in_array($mode, Course::INDIVIDUAL_ACCESS_MODES, true)) {
            foreach ($pricing['market_prices'] ?? [] as $code => $amount) {
                $market = Market::from($code);
                $key = ['course_id' => $course->getKey(), 'market' => $code];
                $current = CoursePrice::query()->where($key)->first();
                $minor = blank($amount) ? null : Money::of((string) $amount, $market->currency())->getMinorAmount()->toInt();
                if ($current?->amount_minor === $minor && ($current === null || $current->currency === $market->currency())) {
                    continue;
                }
                $before['market_prices'][$code] = $current?->amount_minor;
                $after['market_prices'][$code] = $minor;
                if ($minor === null) {
                    $current?->delete();
                } else {
                    CoursePrice::query()->updateOrCreate($key, ['currency' => $market->currency(), 'amount_minor' => $minor]);
                }
            }
        } else {
            foreach (CoursePrice::query()->where('course_id', $course->getKey())->get() as $existingPrice) {
                $before['market_prices'][$existingPrice->market] = $existingPrice->amount_minor;
                $after['market_prices'][$existingPrice->market] = null;
                $existingPrice->delete();
            }
        }

        if ($before !== $after && $this->audit->record('course.pricing_changed', $course, (int) $actor->getKey(), $before, $after) === null) {
            throw new RuntimeException('Unable to record course pricing audit.');
        }
    }

    /**
     * Reads the cover field: an upload replaces, an empty value removes, a stored path equal to the current one is "no change"
     * and any other string is refused (it would point the course at somebody else's file).
     *
     * @param  array<string, mixed>  $data
     * @return array{file: UploadedFile|null, remove: bool}
     */
    private function coverChange(array $data, ?Course $course): array
    {
        if (! array_key_exists('cover_image', $data)) {
            return ['file' => null, 'remove' => false];
        }

        $items = $data['cover_image'] instanceof UploadedFile ? [$data['cover_image']] : array_values((array) $data['cover_image']);
        $items = array_values(array_filter($items, static fn (mixed $item): bool => $item !== null && $item !== ''));
        foreach ($items as $item) {
            if ($item instanceof UploadedFile) {
                return ['file' => $item, 'remove' => false];
            }
        }
        if ($items === []) {
            return ['file' => null, 'remove' => $course?->cover_image !== null];
        }
        if ($course === null || $items !== [$course->cover_image]) {
            throw ValidationException::withMessages(['cover_image' => __('media.invalid_path')]);
        }

        return ['file' => null, 'remove' => false];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function guardPrivileged(User $actor, ?Course $course, array $data): array
    {
        // A form that never touched these fields sends them empty; that means "no change", not a value.
        foreach (['owner_type', 'status', 'featured'] as $optional) {
            if (array_key_exists($optional, $data) && ($data[$optional] === null || $data[$optional] === '')) {
                unset($data[$optional]);
            }
        }

        $canOwnership = $actor->can('panel.admin.access') && $actor->can('course.create');
        $canStatus = $actor->can('panel.admin.access') && $actor->can('course.publish');

        foreach ([[self::OWNERSHIP_FIELDS, $canOwnership], [self::STATUS_FIELDS, $canStatus]] as [$fields, $allowed]) {
            foreach ($fields as $field) {
                if (! array_key_exists($field, $data) || $allowed) {
                    continue;
                }

                // A resubmitted unchanged value is harmless; a different value is a tampering attempt.
                $current = $course?->getAttribute($field);
                $current = $current instanceof CourseStatus ? $current->value : $current;
                if ($course === null || $data[$field] != $current) {
                    throw new AuthorizationException;
                }
                unset($data[$field]);
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{attributes: array<string, mixed>, slugs: array<string, string>}
     */
    private function validate(array $data, ?Course $course, ?int $strictStep): array
    {
        foreach (['ar', 'en'] as $locale) {
            $description = data_get($data, 'description.'.$locale);
            if (is_array($description)) {
                try {
                    $data['description'][$locale] = $description === [] ? null : (string) RichContent::html(RichContent::clean($description));
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages(['description.'.$locale => $exception->errors()['content'] ?? __('content.invalid')]);
                }
            }
        }

        $strict1 = $strictStep === 1;
        $required = static fn (bool $on): array => $on ? ['required'] : ['nullable'];
        $row = static fn (int $max): array => ['nullable', 'string', 'max:'.$max];

        $rules = [
            'title' => ['nullable', 'array'],
            'title.ar' => $row(200), 'title.en' => $row(200),
            'short_description' => ['nullable', 'array'],
            'short_description.ar' => $row(300), 'short_description.en' => $row(300),
            'description' => ['nullable', 'array'],
            'description.ar' => $row(10000), 'description.en' => $row(10000),
            'slug_ar' => ['nullable', 'string', 'max:120', 'regex:/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/u', Rule::unique('courses', 'slug_ar')->ignore($course?->getKey())],
            'slug_en' => ['nullable', 'string', 'max:120', 'regex:/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/u', Rule::unique('courses', 'slug_en')->ignore($course?->getKey())],
            'category_id' => [...$required($strict1), 'integer', Rule::exists('categories', 'id')],
            'level' => [...$required($strict1), Rule::in(self::LEVELS)],
            'content_language' => [...$required($strict1), Rule::in(self::CONTENT_LANGUAGES)],
            'format' => [...$required($strict1), Rule::in(self::FORMATS)],
            'sequence_mode' => ['sometimes', Rule::in([Course::SEQUENCE_FREE, Course::SEQUENCE_SEQUENTIAL])],
            'min_age' => ['nullable', 'integer', 'between:0,120'],
            'max_age' => ['nullable', 'integer', 'between:0,120', 'gte:min_age'],
            'owner_type' => ['sometimes', Rule::in([Course::OWNER_ACADEMY, Course::OWNER_INDEPENDENT])],
            'owner_instructor_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'status' => ['sometimes', Rule::in(self::WIZARD_STATUSES)],
            'featured' => ['sometimes', 'boolean'],
            'seo_title' => ['nullable', 'array:ar,en'], 'seo_title.ar' => $row(160), 'seo_title.en' => $row(160),
            'seo_description' => ['nullable', 'array:ar,en'], 'seo_description.ar' => $row(320), 'seo_description.en' => $row(320),
            'seo_keywords' => ['nullable', 'array:ar,en'], 'seo_keywords.ar' => $row(300), 'seo_keywords.en' => $row(300),
            'canonical_url' => ['nullable', 'string', 'max:500', 'url:https', 'regex:/^\S+$/D'],
            'faqs' => ['nullable', 'array', 'max:'.self::MAX_ROWS],
            'faqs.*.question.ar' => $row(300), 'faqs.*.question.en' => $row(300),
            'faqs.*.answer.ar' => $row(2000), 'faqs.*.answer.en' => $row(2000),
            'requirements' => ['nullable', 'array', 'max:'.self::MAX_ROWS],
            'requirements.*.text.ar' => $row(500), 'requirements.*.text.en' => $row(500),
            'outcomes' => ['nullable', 'array', 'max:'.self::MAX_ROWS],
            'outcomes.*.text.ar' => $row(500), 'outcomes.*.text.en' => $row(500),
        ];

        $validator = Validator::make($data, $rules);
        $validator->after(function ($validator) use ($data, $strict1): void {
            $title = (array) ($data['title'] ?? []);
            if ($strict1 && blank($title['ar'] ?? null) && blank($title['en'] ?? null)) {
                $validator->errors()->add('title.ar', __('validation.required', ['attribute' => __('courses.name')]));
            }
            if (($data['owner_type'] ?? null) === Course::OWNER_INDEPENDENT && blank($data['owner_instructor_id'] ?? null)) {
                $validator->errors()->add('owner_instructor_id', __('validation.required', ['attribute' => __('courses.owner_instructor')]));
            }
        });

        $validated = $validator->validate();

        if (! blank($validated['owner_instructor_id'] ?? null)) {
            $owner = User::query()->find($validated['owner_instructor_id']);
            if ($owner === null || ! $owner->hasAnyRole(['instructor_academy', 'instructor_independent'])) {
                throw ValidationException::withMessages(['owner_instructor_id' => __('courses.invalid_owner')]);
            }
        }

        $attributes = Arr::only($validated, [
            'title', 'short_description', 'description', 'category_id', 'level', 'content_language', 'format',
            'min_age', 'max_age', 'owner_type', 'owner_instructor_id', 'status', 'featured', 'sequence_mode',
            'seo_title', 'seo_description', 'seo_keywords', 'canonical_url',
        ]);
        if (array_key_exists('canonical_url', $attributes)) {
            $attributes['canonical_url'] = blank($attributes['canonical_url']) ? null : trim((string) $attributes['canonical_url']);
        }
        foreach (['title', 'short_description', 'description', 'seo_title', 'seo_description', 'seo_keywords'] as $field) {
            if (array_key_exists($field, $attributes)) {
                $attributes[$field] = array_filter(array_map(static fn ($v) => is_string($v) ? trim($v) : $v, (array) $attributes[$field]), static fn ($v) => $v !== null && $v !== '');
            }
        }
        if (isset($attributes['description'])) {
            $attributes['description'] = array_filter(array_map(CourseDescription::clean(...), $attributes['description']));
        }
        foreach (['faqs', 'requirements', 'outcomes'] as $field) {
            if (array_key_exists($field, $validated)) {
                // The validator rebuilds rows in rule order, so restore the order the user entered (repeater keys may be UUIDs).
                $entered = array_keys((array) ($data[$field] ?? []));
                $ordered = [];
                foreach ($entered as $key) {
                    if (isset($validated[$field][$key])) {
                        $ordered[] = $validated[$field][$key];
                    }
                }
                $attributes[$field] = $this->cleanRows($ordered);
            }
        }

        return ['attributes' => $attributes, 'slugs' => Arr::only($validated, ['slug_ar', 'slug_en'])];
    }

    /**
     * Drops rows with no text in either language and keeps the entered order.
     *
     * @param  array<int, mixed>  $rows
     * @return list<array<string, mixed>>
     */
    private function cleanRows(array $rows): array
    {
        $clean = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            $hasText = false;
            foreach ($row as $key => $value) {
                $row[$key] = array_filter(array_map(static fn ($v) => is_string($v) ? trim($v) : $v, (array) $value), static fn ($v) => $v !== null && $v !== '');
                $hasText = $hasText || $row[$key] !== [];
            }
            if ($hasText) {
                $clean[] = $row;
            }
        }

        return $clean;
    }

    private function uniqueSlug(string $column, Course $course, ?string $source, string $locale): string
    {
        $base = $this->slugify($source ?? '') ?: ($locale === 'ar' ? 'دورة' : 'course');
        $slug = rtrim(mb_substr($base, 0, 120), '-');
        for ($i = 2; Course::query()->where($column, $slug)->when($course->exists, static fn ($q) => $q->whereKeyNot($course->getKey()))->exists(); $i++) {
            $suffix = '-'.$i;
            $slug = rtrim(mb_substr($base, 0, 120 - strlen($suffix)), '-').$suffix;
        }

        return $slug;
    }

    private function slugify(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/[^\p{L}\p{N}]+/u', '-', $text);

        return trim((string) $text, '-');
    }

    /** @param array<string, mixed>|null $before */
    private function auditChange(User $actor, Course $course, bool $created, ?array $before): void
    {
        $status = $course->status->value;
        $after = ['owner_type' => $course->owner_type, 'owner_instructor_id' => $course->owner_instructor_id, 'status' => $status, 'featured' => (bool) $course->featured];

        if ($created) {
            $recorded = $this->audit->record('course.draft_created', $course, (int) $actor->getKey(), null, $after);
        } elseif ($before !== null && (($before['owner_type'] ?? null) !== $after['owner_type'] || ($before['owner_instructor_id'] ?? null) !== $after['owner_instructor_id'])) {
            $recorded = $this->audit->record('course.ownership_changed', $course, (int) $actor->getKey(), $before, Arr::only($after, ['owner_type', 'owner_instructor_id']));
        } else {
            return;
        }

        if ($recorded === null) {
            throw new RuntimeException('Unable to record course audit.');
        }
    }
}
