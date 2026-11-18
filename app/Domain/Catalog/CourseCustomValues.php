<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Catalog\Models\CourseCustomField;
use App\Models\Course;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Validation, cleaning and display of custom field values (T-P3-03 A3). Definitions come from `course_custom_fields`. */
final class CourseCustomValues
{
    /** @return Collection<int, CourseCustomField> */
    public static function definitions(): Collection
    {
        return CourseCustomField::query()->where('is_active', true)->orderBy('sort')->orderBy('id')->get();
    }

    /**
     * Validates submitted values against the active definitions and merges them over the stored ones. A key outside the
     * definitions is dropped. Required fields are enforced only when `$strict` is true (the step was passed).
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>|null  $current
     * @return array<string, mixed>
     */
    public static function validated(array $input, ?array $current, bool $strict): array
    {
        $definitions = self::definitions();
        $rules = [];
        foreach ($definitions as $field) {
            foreach (self::rules($field, $strict) as $path => $fieldRules) {
                $rules['custom.'.$path] = $fieldRules;
            }
        }
        $validated = Validator::make(['custom' => $input], ['custom' => ['nullable', 'array'], ...$rules])
            ->setAttributeNames(self::names($definitions))->validate();
        $clean = (array) ($validated['custom'] ?? []);

        $values = array_diff_key($current ?? [], $definitions->pluck('field_key', 'field_key')->all());
        foreach ($definitions as $field) {
            if (! array_key_exists($field->field_key, $clean) && isset($current[$field->field_key])) {
                $values[$field->field_key] = $current[$field->field_key]; // not submitted: unchanged

                continue;
            }
            $value = $clean[$field->field_key] ?? null;
            if (is_array($value)) {
                $value = array_filter(array_map(static fn ($v) => is_string($v) ? trim($v) : $v, $value), static fn ($v) => $v !== null && $v !== '');
            } elseif (is_string($value)) {
                $value = trim($value);
            }
            if ($field->type === CourseCustomField::TYPE_TOGGLE) {
                $value = $value ? true : null;
            }
            if ($value !== null && $value !== '' && $value !== []) {
                $values[$field->field_key] = $value;
            } elseif ($strict && $field->is_required && $field->type !== CourseCustomField::TYPE_TOGGLE) {
                throw ValidationException::withMessages(['custom.'.$field->field_key => __('validation.required', ['attribute' => (string) $field->getTranslation('label', app()->getLocale())])]);
            }
        }

        return $values;
    }

    /**
     * Required definitions that have no value yet (used by the readiness checklist).
     *
     * @param  array<string, mixed>|null  $values
     * @return list<CourseCustomField>
     */
    public static function missingRequired(?array $values): array
    {
        return self::definitions()->filter(static function (CourseCustomField $field) use ($values): bool {
            if (! $field->is_required || $field->type === CourseCustomField::TYPE_TOGGLE) {
                return false;
            }
            $value = $values[$field->field_key] ?? null;
            if ($field->perLocale()) {
                return ! is_array($value) || (($value['ar'] ?? '') === '' && ($value['en'] ?? '') === '');
            }

            return $value === null || $value === '';
        })->values()->all();
    }

    /**
     * Public fields with a value, for the course page.
     *
     * @return list<array{label: string, value: string}>
     */
    public static function publicRows(Course $course): array
    {
        $values = (array) $course->custom_values;
        $locale = app()->getLocale();
        $rows = [];
        foreach (self::definitions()->where('visibility', CourseCustomField::VISIBILITY_PUBLIC) as $field) {
            $value = $values[$field->field_key] ?? null;
            if (is_array($value)) {
                $value = $value[$locale] ?? ($value['ar'] ?? $value['en'] ?? null);
            }
            if ($value === null || $value === '' || $value === false) {
                continue;
            }
            $rows[] = [
                'label' => (string) $field->getTranslation('label', $locale),
                'value' => $field->type === CourseCustomField::TYPE_TOGGLE ? (string) __('course_wizard.custom_yes') : (string) $value,
            ];
        }

        return $rows;
    }

    /** @return array<string, list<mixed>> rule paths relative to `custom.` */
    private static function rules(CourseCustomField $field, bool $strict): array
    {
        $required = $strict && $field->is_required && $field->type !== CourseCustomField::TYPE_TOGGLE;
        $presence = $required ? 'required' : 'nullable';
        $min = $field->min_value;
        $max = $field->max_value;
        $key = $field->field_key;

        return match ($field->type) {
            CourseCustomField::TYPE_TEXT, CourseCustomField::TYPE_TEXTAREA => $field->perLocale()
                ? [
                    $key => ['nullable', 'array:ar,en'],
                    $key.'.ar' => self::text(false, $min, $max, $field->type),
                    $key.'.en' => self::text(false, $min, $max, $field->type),
                ]
                : [$key => self::text($required, $min, $max, $field->type)],
            CourseCustomField::TYPE_NUMBER => [$key => [$presence, 'numeric', ...($min !== null ? ['min:'.$min] : []), ...($max !== null ? ['max:'.$max] : [])]],
            CourseCustomField::TYPE_SELECT => [$key => [$presence, Rule::in((array) $field->options)]],
            CourseCustomField::TYPE_TOGGLE => [$key => ['nullable', 'boolean']],
            CourseCustomField::TYPE_URL => [$key => [$presence, 'string', 'max:500', 'url:https']],
            default => [$key => [$presence, 'date_format:Y-m-d']],
        };
    }

    /** @return list<mixed> */
    private static function text(bool $required, ?int $min, ?int $max, string $type): array
    {
        return [$required ? 'required' : 'nullable', 'string', ...($min !== null ? ['min:'.$min] : []), 'max:'.($max ?? ($type === CourseCustomField::TYPE_TEXTAREA ? 5000 : 500))];
    }

    /**
     * @param  Collection<int, CourseCustomField>  $definitions
     * @return array<string, string>
     */
    private static function names(Collection $definitions): array
    {
        $names = [];
        foreach ($definitions as $field) {
            $label = (string) $field->getTranslation('label', app()->getLocale());
            $names['custom.'.$field->field_key] = $label;
            $names['custom.'.$field->field_key.'.ar'] = $label.' (AR)';
            $names['custom.'.$field->field_key.'.en'] = $label.' (EN)';
        }

        return $names;
    }
}
