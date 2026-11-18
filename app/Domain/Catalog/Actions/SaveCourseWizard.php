<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Catalog\CourseStatus;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Media\LessonPublishRule;
use App\Domain\Media\MediaProviders;
use App\Domain\Shared\Markets\Market;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\MediaAsset;
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

/**
 * A course draft may be saved at any point with
 * incomplete data ($complete = false); passing a step ($complete = true) enforces its required fields.
 * Ownership, status and featured are changed only by users who hold the matching permission.
 */
final class SaveCourseWizard
{
    public const int PRICING_STEP = 3;

    public const int MEDIA_STEP = 4;

    public const int LAST_STEP = self::MEDIA_STEP;

    public const array LEVELS = ['beginner', 'intermediate', 'advanced', 'all'];

    public const array FORMATS = ['recorded', 'live', 'hybrid'];

    public const array CONTENT_LANGUAGES = ['ar', 'en', 'both'];

    public const array VIDEO_LINK_PROVIDERS = ['youtube', 'vimeo'];

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
        }

        $data = $this->guardPrivileged($actor, $course, $data);
        $clean = $this->validate($data, $course, $complete ? $step : null);

        return DB::transaction(function () use ($actor, $clean, $course, $step, $complete, $data): Course {
            $record = $course === null ? new Course : Course::query()->lockForUpdate()->findOrFail($course->getKey());
            $pricing = $this->validatePricing($actor, $record, $data, $step >= self::PRICING_STEP && $complete);
            $videos = $step >= self::MEDIA_STEP ? $this->validateVideos($record, $data, $complete) : [];
            $before = $record->exists ? Arr::only($record->getAttributes(), ['owner_type', 'owner_instructor_id', 'status', 'featured']) : null;
            $record->fill([...$clean['attributes'], ...$videos]);
            $record->slug_ar = $clean['slugs']['slug_ar'] ?? $record->slug_ar ?? $this->uniqueSlug('slug_ar', $record, $record->getTranslation('title', 'ar', false) ?: $record->getTranslation('title', 'en', false), 'ar');
            $record->slug_en = $clean['slugs']['slug_en'] ?? $record->slug_en ?? $this->uniqueSlug('slug_en', $record, $record->getTranslation('title', 'en', false) ?: $record->getTranslation('title', 'ar', false), 'en');

            $completed = is_array($record->wizard_completed) ? $record->wizard_completed : [];
            if ($complete) {
                $completed[(string) $step] = true;
            }
            $record->wizard_completed = $completed;
            $record->wizard_step = min(self::LAST_STEP, max((int) $record->wizard_step, $complete ? $step + 1 : $step));
            $created = ! $record->exists;
            $record->save();

            $this->auditChange($actor, $record, $created, $before);
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
     * @return array<string, string|null>
     */
    private function validateVideos(Course $course, array $data, bool $complete): array
    {
        $attributes = [];
        foreach (['overview_video', 'preview_video'] as $video) {
            $provider = $video.'_provider';
            $url = $video.'_url';
            $submitted = Arr::only($data, [$provider, $url]);
            if ($submitted === [] && ! $complete) {
                continue;
            }

            $values = array_map(
                static fn (mixed $value): mixed => is_string($value) && trim($value) === '' ? null : $value,
                array_replace($course->only([$provider, $url]), $submitted),
            );
            $validated = Validator::make($values, [
                $provider => ['nullable', 'required_with:'.$url, 'string', Rule::in(self::VIDEO_LINK_PROVIDERS)],
                $url => ['nullable', ...($complete ? ['required_with:'.$provider] : []), 'string', 'max:2048'],
            ], [], [$provider => __('courses.'.$provider), $url => __('courses.'.$url)])->validate();

            if ($validated[$url] !== null) {
                $driver = MediaProviders::for($validated[$provider]);
                try {
                    $source = $driver->validate(['url' => $validated[$url]]);
                } catch (ValidationException $error) {
                    throw ValidationException::withMessages([$url => $error->errors()['url'] ?? [__('media.invalid_url')]]);
                }

                $validated[$url] = $driver->descriptor(new MediaAsset($source))->embedUrl
                    ?? throw ValidationException::withMessages([$url => __('media.invalid_url')]);
            }
            if ($submitted !== []) {
                $attributes += $validated;
            }
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{access_mode?: string|null, market_prices?: array<string, string|null>}
     */
    private function validatePricing(User $actor, Course $course, array $data, bool $complete): array
    {
        $fields = Arr::only($data, ['access_mode', 'market_prices']);
        if (in_array($fields['access_mode'] ?? null, [null, ''], true)) {
            unset($fields['access_mode']);
        }
        $canSetPricing = self::canSetPricing($actor);
        $prices = $fields['market_prices'] ?? [];
        if ((! $canSetPricing || ! isset($fields['access_mode'])) && is_array($prices)
            && array_filter($prices, static fn ($amount): bool => $amount !== null && $amount !== '') === []) {
            unset($fields['market_prices']);
        }
        if (! $canSetPricing) {
            if ($fields !== []) {
                throw new AuthorizationException;
            }

            return [];
        }

        $mode = $fields['access_mode'] ?? $course->access_mode;
        $individual = in_array($mode, Course::INDIVIDUAL_ACCESS_MODES, true);
        if ($complete) {
            $fields['access_mode'] = $mode;
        }
        $rules = [
            'access_mode' => [$complete ? 'required' : 'nullable', Rule::in([Course::ACCESS_FREE, ...Course::PAID_ACCESS_MODES])],
            'market_prices' => ['nullable', 'array:'.implode(',', array_column(Market::cases(), 'value'))],
        ];
        foreach (Market::cases() as $market) {
            $rules['market_prices.'.$market->value] = [
                $individual && $complete && $market->isActive() ? 'required' : 'nullable',
                'regex:/^\d{1,9}(?:\.\d{1,2})?$/D', 'numeric', 'gt:0',
            ];
        }

        if (! $individual) {
            unset($fields['market_prices']);
        }

        return Validator::make($fields, $rules)->validate();
    }

    /** @param array{access_mode?: string|null, market_prices?: array<string, string|null>} $pricing */
    private function savePricing(User $actor, Course $course, array $pricing): void
    {
        $mode = $pricing['access_mode'] ?? $course->access_mode;
        $before = ['access_mode' => $course->access_mode, 'market_prices' => []];
        $after = ['access_mode' => $mode, 'market_prices' => []];
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
        } elseif ($before['access_mode'] !== $mode) {
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
        if ($course !== null && ($data['status'] ?? null) === $course->status->value) {
            unset($data['status']);
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
        ]);
        foreach (['title', 'short_description', 'description'] as $field) {
            if (array_key_exists($field, $attributes)) {
                $attributes[$field] = array_filter(array_map(static fn ($v) => is_string($v) ? trim($v) : $v, (array) $attributes[$field]), static fn ($v) => $v !== null && $v !== '');
            }
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
        $slug = $base;
        for ($i = 2; Course::query()->where($column, $slug)->when($course->exists, static fn ($q) => $q->whereKeyNot($course->getKey()))->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return mb_substr($slug, 0, 120);
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
