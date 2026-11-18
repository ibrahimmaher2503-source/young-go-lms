<?php

declare(strict_types=1);

namespace App\Filament\Resources\CourseCustomFields;

use App\Domain\Catalog\Actions\SaveCourseCustomField;
use App\Domain\Catalog\Models\CourseCustomField;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/** The create/edit modal for custom field definitions. Every write goes through SaveCourseCustomField. */
final class CourseCustomFieldForm
{
    public static function configure(Action $action): Action
    {
        return $action->schema([
            TextInput::make('field_key')->label(__('course_wizard.fields.key'))->helperText(__('course_wizard.fields.key_help'))
                ->maxLength(60)->required()->disabled(static fn (?CourseCustomField $record): bool => $record !== null)->dehydrated(static fn (?CourseCustomField $record): bool => $record === null)
                ->extraInputAttributes(['dir' => 'ltr']),
            Grid::make(['default' => 1, 'md' => 2])->schema([
                TextInput::make('label.ar')->label(__('course_wizard.fields.label').' (AR)')->maxLength(120)->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar']),
                TextInput::make('label.en')->label(__('course_wizard.fields.label').' (EN)')->maxLength(120)->extraInputAttributes(['dir' => 'ltr', 'lang' => 'en']),
            ]),
            Select::make('type')->label(__('course_wizard.fields.type'))->required()->native(false)->live()
                ->options(array_combine(CourseCustomField::TYPES, array_map(static fn (string $type): string => __('course_wizard.fields.types.'.$type), CourseCustomField::TYPES))),
            Repeater::make('options')->label(__('course_wizard.fields.options'))->simple(TextInput::make('value')->maxLength(80)->required())
                ->maxItems(SaveCourseCustomField::MAX_OPTIONS)->defaultItems(0)
                ->visible(static fn (Get $get): bool => $get('type') === CourseCustomField::TYPE_SELECT),
            Grid::make(['default' => 1, 'md' => 2])->schema([
                TextInput::make('min_value')->label(__('course_wizard.fields.min'))->numeric()->minValue(0),
                TextInput::make('max_value')->label(__('course_wizard.fields.max'))->numeric()->minValue(0),
            ])->visible(static fn (Get $get): bool => in_array($get('type'), [CourseCustomField::TYPE_TEXT, CourseCustomField::TYPE_TEXTAREA, CourseCustomField::TYPE_NUMBER], true)),
            Select::make('visibility')->label(__('course_wizard.fields.visibility'))->required()->native(false)
                ->options([CourseCustomField::VISIBILITY_PUBLIC => __('course_wizard.fields.public'), CourseCustomField::VISIBILITY_INTERNAL => __('course_wizard.fields.internal')]),
            Toggle::make('is_required')->label(__('course_wizard.fields.required')),
            Toggle::make('is_translatable')->label(__('course_wizard.fields.translatable')),
            Toggle::make('is_active')->label(__('course_wizard.fields.active')),
            TextInput::make('sort')->label(__('course_wizard.fields.sort'))->numeric()->minValue(0)->maxValue(65535),
        ])->fillForm(static function (?CourseCustomField $record): array {
            if ($record === null) {
                return ['visibility' => CourseCustomField::VISIBILITY_INTERNAL, 'is_active' => true, 'is_required' => false, 'is_translatable' => false, 'sort' => 0, 'options' => []];
            }

            return [
                'field_key' => $record->field_key, 'type' => $record->type,
                'label' => ['ar' => $record->getTranslation('label', 'ar', false), 'en' => $record->getTranslation('label', 'en', false)],
                'options' => array_map(static fn (string $option): array => ['value' => $option], (array) $record->options),
                'min_value' => $record->min_value, 'max_value' => $record->max_value, 'visibility' => $record->visibility,
                'is_required' => $record->is_required, 'is_translatable' => $record->is_translatable, 'is_active' => $record->is_active, 'sort' => $record->sort,
            ];
        })->action(static function (array $data, Action $action, ?CourseCustomField $record): void {
            $actor = Auth::user();
            abort_unless($actor instanceof User, 401);
            $data['options'] = array_values(array_map(static fn ($row): string => (string) (is_array($row) ? ($row['value'] ?? '') : $row), (array) ($data['options'] ?? [])));
            try {
                app(SaveCourseCustomField::class)($actor, $data, $record);
            } catch (ValidationException $exception) {
                Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();
                $action->halt();

                return;
            }
            Notification::make()->success()->title(__('course_wizard.fields.saved'))->send();
        });
    }
}
