<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Catalog\Models\CourseCustomField;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Creates or edits an admin-defined course field (PRD 9.2 step 6): type, validation (required, min, max, choices),
 * visibility (public or staff only) and whether it has separate AR/EN values. The key is fixed once created. Needs
 * `course.publish` on the admin panel; every change is audited.
 */
final class SaveCourseCustomField
{
    public const int MAX_OPTIONS = 30;

    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function __invoke(User $actor, array $data, ?CourseCustomField $field = null): CourseCustomField
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('panel.admin.access') || ! $actor->can('course.publish')) {
            throw new AuthorizationException;
        }

        $rules = [
            'field_key' => [$field === null ? 'required' : 'nullable', 'string', 'regex:/^[a-z][a-z0-9_]{1,59}$/D', Rule::unique('course_custom_fields', 'field_key')->ignore($field?->getKey())],
            'type' => ['required', Rule::in(CourseCustomField::TYPES)],
            'label' => ['required', 'array'],
            'label.ar' => ['nullable', 'string', 'max:120'], 'label.en' => ['nullable', 'string', 'max:120'],
            'options' => ['nullable', 'array', 'max:'.self::MAX_OPTIONS], 'options.*' => ['string', 'max:80'],
            'is_required' => ['boolean'], 'is_translatable' => ['boolean'], 'is_active' => ['boolean'],
            'min_value' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'max_value' => ['nullable', 'integer', 'min:0', 'max:1000000', 'gte:min_value'],
            'visibility' => ['required', Rule::in([CourseCustomField::VISIBILITY_PUBLIC, CourseCustomField::VISIBILITY_INTERNAL])],
            'sort' => ['nullable', 'integer', 'between:0,65535'],
        ];
        $validator = Validator::make($data, $rules);
        $validator->after(function ($validator) use ($data): void {
            $label = (array) ($data['label'] ?? []);
            if (blank($label['ar'] ?? null) && blank($label['en'] ?? null)) {
                $validator->errors()->add('label.ar', __('validation.required', ['attribute' => __('course_wizard.fields.label')]));
            }
            $options = array_values(array_unique(array_filter(array_map(static fn ($o) => is_string($o) ? trim($o) : '', (array) ($data['options'] ?? [])))));
            if (($data['type'] ?? null) === CourseCustomField::TYPE_SELECT && count($options) < 2) {
                $validator->errors()->add('options', __('course_wizard.fields.options_needed'));
            }
        });
        $clean = $validator->validate();

        $options = ($clean['type'] === CourseCustomField::TYPE_SELECT)
            ? array_values(array_unique(array_filter(array_map(static fn ($o) => trim((string) $o), (array) ($clean['options'] ?? [])))))
            : null;
        $label = array_filter(array_map(static fn ($v) => trim((string) $v), (array) $clean['label']), static fn (string $v): bool => $v !== '');

        return DB::transaction(function () use ($actor, $clean, $field, $options, $label): CourseCustomField {
            $record = $field === null ? new CourseCustomField : CourseCustomField::query()->lockForUpdate()->findOrFail($field->getKey());
            $before = $record->exists ? Arr::except($record->getAttributes(), ['created_at', 'updated_at']) : null;
            $record->fill([
                'type' => $clean['type'], 'options' => $options, 'visibility' => $clean['visibility'],
                'is_required' => (bool) ($clean['is_required'] ?? false), 'is_translatable' => (bool) ($clean['is_translatable'] ?? false),
                'is_active' => (bool) ($clean['is_active'] ?? true), 'sort' => (int) ($clean['sort'] ?? 0),
                'min_value' => $clean['min_value'] ?? null, 'max_value' => $clean['max_value'] ?? null,
            ]);
            $record->setTranslations('label', $label);
            if ($field === null) {
                $record->field_key = $clean['field_key'];
            }
            $record->save();

            if ($this->audit->record('course_custom_field.saved', $record, (int) $actor->getKey(), $before, Arr::except($record->getAttributes(), ['created_at', 'updated_at'])) === null) {
                throw new RuntimeException('Unable to record custom field audit.');
            }

            return $record;
        });
    }
}
