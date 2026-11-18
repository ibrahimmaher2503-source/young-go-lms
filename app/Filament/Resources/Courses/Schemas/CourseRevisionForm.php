<?php

declare(strict_types=1);

namespace App\Filament\Resources\Courses\Schemas;

use App\Domain\Catalog\Actions\SaveCourseWizard;
use App\Domain\Catalog\Revisions\RevisionSnapshot;
use App\Filament\Shared\Forms\TranslatableTabs;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

/** T-P3-07: the revision editor (slide-over on the revision page). Validation and normalization live in RevisionContent. */
final class CourseRevisionForm
{
    /** @return list<mixed> */
    public static function make(): array
    {
        return [
            Section::make(__('course_revisions.course_data'))->icon('heroicon-o-information-circle')->schema([
                Group::make([
                    TranslatableTabs::make(static fn (string $locale): array => [
                        TextInput::make("title.{$locale}")->label(__('courses.name'))->maxLength(200)
                            ->extraInputAttributes(['dir' => $locale === 'ar' ? 'rtl' : 'ltr', 'lang' => $locale]),
                        Textarea::make("short_description.{$locale}")->label(__('courses.short_description'))->maxLength(300)->rows(2)
                            ->extraInputAttributes(['dir' => $locale === 'ar' ? 'rtl' : 'ltr', 'lang' => $locale]),
                        RichEditor::make("description.{$locale}")->label(__('courses.description'))
                            ->toolbarButtons([['bold', 'italic', 'link'], ['h2', 'h3'], ['bulletList', 'orderedList', 'blockquote'], ['undo', 'redo']])
                            ->extraAttributes(['dir' => $locale === 'ar' ? 'rtl' : 'ltr', 'lang' => $locale]),
                    ]),
                    Grid::make(['default' => 1, 'md' => 2])->schema([
                        Select::make('category_id')->label(__('courses.category'))->options(static fn (): array => CourseWizardSteps::categoryOptions())->searchable()->required(),
                        Select::make('level')->label(__('courses.level'))->required()->options(self::labels('levels', SaveCourseWizard::LEVELS)),
                        Select::make('content_language')->label(__('courses.content_language'))->required()->options(self::labels('languages', SaveCourseWizard::CONTENT_LANGUAGES)),
                        TextInput::make('min_age')->label(__('courses.min_age'))->numeric()->minValue(0)->maxValue(120),
                        TextInput::make('max_age')->label(__('courses.max_age'))->numeric()->minValue(0)->maxValue(120),
                    ]),
                    ...CourseWizardSteps::information(),
                ])->statePath('course'),
            ]),
            Section::make(__('course_revisions.curriculum_data'))->icon('heroicon-o-queue-list')->description(__('course_revisions.curriculum_help'))->schema([
                Repeater::make('sections')->label(__('course_revisions.sections'))->reorderable()->addActionLabel(__('curriculum.add_section'))->collapsible()->maxItems(100)->defaultItems(0)
                    ->itemLabel(static fn (array $state): ?string => RevisionSnapshot::label($state['title'] ?? []) ?: null)
                    ->schema([
                        Hidden::make('id'),
                        Grid::make(['default' => 1, 'md' => 3])->schema([
                            TextInput::make('title.ar')->label(__('curriculum.title').' (AR)')->maxLength(150)->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar']),
                            TextInput::make('title.en')->label(__('curriculum.title').' (EN)')->maxLength(150)->extraInputAttributes(['dir' => 'ltr', 'lang' => 'en']),
                            Toggle::make('published')->label(__('curriculum.status_published')),
                        ]),
                        Repeater::make('items')->label(__('course_revisions.items'))->reorderable()->addActionLabel(__('course_revisions.add_item'))->collapsible()->maxItems(300)->defaultItems(0)
                            ->itemLabel(static fn (array $state): ?string => RevisionSnapshot::label($state['title'] ?? []) ?: null)
                            ->schema([
                                Hidden::make('id'),
                                Hidden::make('existing_kind'),
                                Grid::make(['default' => 1, 'md' => 2])->schema([
                                    Select::make('kind')->label(__('course_revisions.item_kind'))->native(false)->default('lesson')
                                        ->options(array_combine(RevisionSnapshot::EDITABLE_KINDS, array_map(static fn (string $k): string => (string) __('course_revisions.kind.'.$k), RevisionSnapshot::EDITABLE_KINDS)))
                                        ->visible(static fn (Get $get): bool => blank($get('id')))->live(),
                                    Select::make('lesson_type')->label(__('curriculum.lesson_type'))->native(false)->default('text')
                                        ->options(array_combine(Lesson::TYPES, array_map(static fn (string $t): string => (string) __('curriculum.types.'.$t), Lesson::TYPES)))
                                        ->visible(static fn (Get $get): bool => blank($get('id')) && $get('kind') === 'lesson'),
                                    TextInput::make('title.ar')->label(__('curriculum.title').' (AR)')->maxLength(150)->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar'])
                                        ->disabled(static fn (Get $get): bool => $get('existing_kind') === 'live_session'),
                                    TextInput::make('title.en')->label(__('curriculum.title').' (EN)')->maxLength(150)->extraInputAttributes(['dir' => 'ltr', 'lang' => 'en'])
                                        ->disabled(static fn (Get $get): bool => $get('existing_kind') === 'live_session'),
                                    Textarea::make('description.ar')->label(__('courses.description').' (AR)')->rows(2)->maxLength(5000)->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar'])
                                        ->disabled(static fn (Get $get): bool => $get('existing_kind') === 'live_session'),
                                    Textarea::make('description.en')->label(__('courses.description').' (EN)')->rows(2)->maxLength(5000)->extraInputAttributes(['dir' => 'ltr', 'lang' => 'en'])
                                        ->disabled(static fn (Get $get): bool => $get('existing_kind') === 'live_session'),
                                    Select::make('status')->label(__('course_revisions.item_status'))->native(false)->default(CurriculumItem::STATUS_DRAFT)
                                        ->options([CurriculumItem::STATUS_DRAFT => __('curriculum.status_draft'), CurriculumItem::STATUS_PUBLISHED => __('curriculum.status_published')]),
                                ]),
                            ]),
                    ]),
            ]),
        ];
    }

    /**
     * Revision content to form state (the hidden existing_kind marks rows whose text a revision cannot edit).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function fill(array $payload): array
    {
        $sections = [];
        foreach ((array) ($payload['sections'] ?? []) as $section) {
            $items = [];
            foreach ((array) $section['items'] as $item) {
                $items[] = $item + ['existing_kind' => $item['id'] === null ? null : $item['kind']];
            }
            $sections[] = ['id' => $section['id'], 'title' => $section['title'], 'published' => $section['published'], 'items' => $items];
        }

        return ['course' => (array) ($payload['course'] ?? []), 'sections' => $sections];
    }

    /**
     * @param  list<string>  $values
     * @return array<string, string>
     */
    private static function labels(string $group, array $values): array
    {
        $labels = [];
        foreach ($values as $value) {
            $labels[$value] = (string) __("courses.{$group}.{$value}");
        }

        return $labels;
    }
}
