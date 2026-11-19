<?php

declare(strict_types=1);

namespace App\Filament\Resources\Courses\Schemas;

use App\Domain\Assessment\QuizPassMark;
use App\Domain\Catalog\Actions\SaveCourseWizard;
use App\Domain\Catalog\CourseCustomValues;
use App\Domain\Catalog\CourseMedia;
use App\Domain\Catalog\Models\CourseCustomField;
use App\Domain\Shared\Markets\Market;
use App\Filament\Shared\Forms\SeoFieldsGroup;
use App\Filament\Shared\Forms\TranslatableTabs;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\Facades\Auth;

/** Native wizard steps. Validation lives in SaveCourseWizard. */
final class CourseWizardSteps
{
    /**
     * @param  Closure(int): mixed  $persist  saves the current form state when a step is passed
     * @param  list<Action>  $finishActions  submit-for-review and publish buttons shown on the Finish step
     * @return list<Step>
     */
    public static function make(Closure $persist, array $finishActions = []): array
    {
        return [
            Step::make('basic')->label(__('courses.step_basic'))
                ->afterValidation(static fn () => $persist(1))->schema([self::withProgress(self::basic())]),
            Step::make('information')->label(__('courses.step_information'))
                ->afterValidation(static fn () => $persist(2))->schema([self::withProgress(self::information())]),
            Step::make('pricing')->label(__('courses.step_pricing'))
                ->afterValidation(static fn () => $persist(3))->schema([self::withProgress([self::pricing()])]),
            Step::make('media')->label(__('course_wizard.step_media'))
                ->afterValidation(static fn () => $persist(4))->schema([self::withProgress([self::media()])]),
            Step::make('seo')->label(__('course_wizard.step_seo'))
                ->afterValidation(static fn () => $persist(5))->schema([self::withProgress([SeoFieldsGroup::make()])]),
            Step::make('custom')->label(__('course_wizard.step_custom'))
                ->afterValidation(static fn () => $persist(6))->schema([self::withProgress(self::custom())]),
            Step::make('finish')->label(__('course_wizard.step_finish'))
                ->schema([self::withProgress([self::finish($finishActions)])]),
        ];
    }

    /** @param list<mixed> $fields */
    private static function withProgress(array $fields): Grid
    {
        return Grid::make(['default' => 1, 'xl' => 3])->schema([
            Group::make($fields)->columnSpan(['xl' => 2]),
            Section::make(__('courses.setup_progress'))->icon('heroicon-o-squares-2x2')->columnSpan(['xl' => 1])->schema([
                View::make('filament.courses.wizard-progress')->viewData(static fn ($livewire): array => ['course' => $livewire->record ?? null]),
            ]),
        ]);
    }

    private static function pricing(): Section
    {
        $user = Auth::user();
        $section = Section::make(__('courses.step_pricing'))->icon('heroicon-o-banknotes');
        if (! $user instanceof User || ! SaveCourseWizard::canSetPricing($user)) {
            return $section->schema([Text::make(__('courses.pricing_managed'))]);
        }

        return $section->schema([
            Select::make('access_mode')->label(__('courses.access'))->live()->markAsRequired()->options([
                Course::ACCESS_FREE => __('courses.free'),
                Course::ACCESS_PAID => __('courses.access_individual'),
                Course::ACCESS_SUBSCRIPTION => __('courses.access_subscription'),
                Course::ACCESS_BOTH => __('courses.access_both'),
            ]),
            Grid::make(['default' => 1, 'md' => 2])->schema(array_map(
                static fn (Market $market): TextInput => TextInput::make('market_prices.'.$market->value)
                    ->label(__('courses.market_price.'.$market->value))
                    ->suffix($market->currency())
                    ->inputMode('decimal')
                    ->maxLength(12)
                    ->markAsRequired($market->isActive())
                    ->helperText($market->isActive() ? null : __('courses.market_price_disabled')),
                Market::cases(),
            ))->visible(static fn (Get $get): bool => in_array($get('access_mode'), Course::INDIVIDUAL_ACCESS_MODES, true)),
            Grid::make(['default' => 1, 'md' => 2])->schema([
                TextInput::make('access_duration_days')->label(__('course_wizard.access_duration'))->helperText(__('course_wizard.access_duration_help'))
                    ->numeric()->integer()->minValue(1)->maxValue(3650),
                Toggle::make('bundle_eligible')->label(__('course_wizard.bundle_eligible'))->default(true),
                Toggle::make('manual_access_allowed')->label(__('course_wizard.manual_access_allowed'))->default(true),
            ]),
        ]);
    }

    /** @return list<Section> */
    private static function videoAndThumbnailFields(): array
    {
        $providers = array_combine(
            SaveCourseWizard::VIDEO_LINK_PROVIDERS,
            array_map(static fn (string $provider): string => __('courses.video_providers.'.$provider), SaveCourseWizard::VIDEO_LINK_PROVIDERS),
        );

        $videos = array_map(
            static fn (string $video): Section => Section::make(__('courses.'.$video))
                ->icon('heroicon-o-video-camera')->columns(['default' => 1, 'md' => 2])->schema([
                    Select::make($video.'_provider')->label(__('courses.'.$video.'_provider'))->options($providers),
                    TextInput::make($video.'_url')->label(__('courses.'.$video.'_url'))
                        ->url()->maxLength(2048)->extraInputAttributes(['dir' => 'ltr']),
                ]),
            ['overview_video', 'preview_video'],
        );

        return [
            ...$videos,
            Section::make(__('courses.thumbnail'))->icon('heroicon-o-photo')->schema([
                FileUpload::make('thumbnail_path')->label(__('courses.thumbnail'))
                    ->disk('public')->visibility('public')
                    ->acceptedFileTypes(SaveCourseWizard::THUMBNAIL_MIME_TYPES)
                    ->imageEditor()->imageAspectRatio('16:9')->automaticallyOpenImageEditorForAspectRatio()
                    ->maxSize(SaveCourseWizard::THUMBNAIL_MAX_KILOBYTES)->storeFiles(false)->preventFilePathTampering()
                    ->getUploadedFileUsing(static fn (FileUpload $component, string $file): ?array => $component->isFilePathAuthorized($file)
                        ? $component->getUploadedFile($file, null)
                        : null),
            ]),
        ];
    }

    private static function media(): Section
    {
        return Section::make(__('course_wizard.media_section'))->icon('heroicon-o-photo')->schema([
            ...self::videoAndThumbnailFields(),
            FileUpload::make('cover_image')->label(__('course_wizard.cover_image'))->helperText(__('course_wizard.cover_help'))
                ->disk('public')->directory('courses')->visibility('public')->image()
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(CourseMedia::MAX_KILOBYTES)
                ->imageEditor()->imageEditorAspectRatios(['16:9'])->imageAspectRatio('16:9')->automaticallyCropImagesToAspectRatio()
                ->storeFiles(false)->preventFilePathTampering(),
            Grid::make(['default' => 1, 'md' => 2])->schema([
                Select::make('promo_video.provider')->label(__('course_wizard.promo_provider'))->native(false)
                    ->options(array_combine(CourseMedia::PROMO_PROVIDERS, array_map(static fn (string $p): string => __('course_wizard.provider.'.$p), CourseMedia::PROMO_PROVIDERS))),
                TextInput::make('promo_video.url')->label(__('course_wizard.promo_url'))->helperText(__('course_wizard.promo_help'))
                    ->url()->maxLength(2048)->extraInputAttributes(['dir' => 'ltr']),
            ]),
        ]);
    }

    /** @return list<mixed> */
    private static function custom(): array
    {
        $fields = CourseCustomValues::definitions();
        $section = Section::make(__('course_wizard.step_custom'))->icon('heroicon-o-adjustments-horizontal');

        return [$fields->isEmpty()
            ? $section->schema([Text::make(__('course_wizard.custom_empty'))])
            : $section->schema($fields->map(static fn (CourseCustomField $field) => self::customField($field))->all())];
    }

    private static function customField(CourseCustomField $field): mixed
    {
        $name = 'custom.'.$field->field_key;
        $label = (string) $field->getTranslation('label', app()->getLocale());

        if ($field->perLocale()) {
            $make = static fn (string $locale) => ($field->type === CourseCustomField::TYPE_TEXTAREA ? Textarea::make($name.'.'.$locale)->rows(3) : TextInput::make($name.'.'.$locale))
                ->label($label.' ('.strtoupper($locale).')')->extraInputAttributes(['dir' => $locale === 'ar' ? 'rtl' : 'ltr', 'lang' => $locale]);

            return Grid::make(['default' => 1, 'md' => 2])->schema([$make('ar'), $make('en')]);
        }

        $component = match ($field->type) {
            CourseCustomField::TYPE_TEXTAREA => Textarea::make($name)->rows(3),
            CourseCustomField::TYPE_NUMBER => TextInput::make($name)->numeric(),
            CourseCustomField::TYPE_SELECT => Select::make($name)->native(false)->options(array_combine((array) $field->options, (array) $field->options)),
            CourseCustomField::TYPE_TOGGLE => Toggle::make($name),
            CourseCustomField::TYPE_URL => TextInput::make($name)->url()->extraInputAttributes(['dir' => 'ltr']),
            CourseCustomField::TYPE_DATE => DatePicker::make($name)->native(false)->format('Y-m-d'),
            default => TextInput::make($name),
        };

        return $component->label($label)->markAsRequired($field->is_required && $field->type !== CourseCustomField::TYPE_TOGGLE);
    }

    /** @param list<Action> $actions */
    private static function finish(array $actions): Section
    {
        return Section::make(__('course_wizard.finish_summary'))->icon('heroicon-o-flag')->schema([
            View::make('filament.courses.wizard-finish')->viewData(static fn ($livewire): array => ['course' => $livewire->record ?? null]),
            Actions::make($actions),
        ]);
    }

    /** @return list<mixed> */
    private static function basic(): array
    {
        $user = Auth::user();
        $admin = $user instanceof User && $user->can('panel.admin.access');
        $canOwnership = $admin && $user->can('course.create');
        $canStatus = $admin && $user->can('course.publish');

        return [
            Section::make(__('courses.basic_data'))->icon('heroicon-o-information-circle')->schema([
                TranslatableTabs::make(static fn (string $locale): array => [
                    TextInput::make("title.{$locale}")->label(__('courses.name'))->maxLength(200)->markAsRequired()
                        ->extraInputAttributes(['dir' => $locale === 'ar' ? 'rtl' : 'ltr', 'lang' => $locale]),
                    TextInput::make($locale === 'ar' ? 'slug_ar' : 'slug_en')->label(__("courses.slug_{$locale}"))->helperText(__('courses.slug_help'))->maxLength(120)
                        ->extraInputAttributes(['dir' => $locale === 'ar' ? 'rtl' : 'ltr', 'lang' => $locale]),
                    Textarea::make("short_description.{$locale}")->label(__('courses.short_description'))->maxLength(300)->rows(2)
                        ->extraInputAttributes(['dir' => $locale === 'ar' ? 'rtl' : 'ltr', 'lang' => $locale]),
                    RichEditor::make("description.{$locale}")->label(__('courses.description'))
                        ->toolbarButtons([['bold', 'italic', 'link'], ['h2', 'h3'], ['bulletList', 'orderedList', 'blockquote'], ['undo', 'redo']])
                        ->extraAttributes(['dir' => $locale === 'ar' ? 'rtl' : 'ltr', 'lang' => $locale]),
                ]),
                Grid::make(['default' => 1, 'md' => 2])->schema([
                    Select::make('category_id')->label(__('courses.category'))->options(static fn (): array => self::categoryOptions())->searchable()->markAsRequired(),
                    Select::make('level')->label(__('courses.level'))->markAsRequired()->options(self::labelled('levels', SaveCourseWizard::LEVELS)),
                    Select::make('content_language')->label(__('courses.content_language'))->markAsRequired()->options(self::labelled('languages', SaveCourseWizard::CONTENT_LANGUAGES)),
                    Select::make('format')->label(__('courses.format'))->markAsRequired()->options(self::labelled('formats', SaveCourseWizard::FORMATS)),
                    Select::make('sequence_mode')->label(__('courses.sequence_mode'))->helperText(__('courses.sequence_mode_help'))->default('free')->native(false)
                        ->options(['free' => __('courses.sequence.free'), 'sequential' => __('courses.sequence.sequential')]),
                    TextInput::make('quiz_pass_mark_percent')
                        ->label(__('completion.quiz_pass_mark'))
                        ->helperText(__('completion.quiz_pass_mark_help', ['percent' => QuizPassMark::default()]))
                        ->placeholder((string) QuizPassMark::default())
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->maxValue(100),
                    TextInput::make('min_age')->label(__('courses.min_age'))->numeric()->minValue(0)->maxValue(120),
                    TextInput::make('max_age')->label(__('courses.max_age'))->numeric()->minValue(0)->maxValue(120),
                    Select::make('owner_type')->label(__('courses.ownership'))->visible($canOwnership)->live()
                        ->options([Course::OWNER_ACADEMY => __('courses.academy'), Course::OWNER_INDEPENDENT => __('courses.independent')]),
                    Select::make('owner_instructor_id')->label(__('courses.owner_instructor'))->searchable()
                        ->visible(static fn ($get): bool => $canOwnership && $get('owner_type') === Course::OWNER_INDEPENDENT)
                        ->options(static fn (): array => User::query()->role(['instructor_academy', 'instructor_independent'])->orderBy('name')->pluck('name', 'id')->all()),
                    Select::make('status')->label(__('courses.status'))->visible($canStatus)
                        ->options(static fn (?Course $record): array => self::labelled('statuses', array_unique([
                            ...SaveCourseWizard::WIZARD_STATUSES,
                            ...($record === null ? [] : [$record->status->value]),
                        ]))),
                    Toggle::make('featured')->label(__('courses.featured'))->visible($canStatus),
                ]),
            ]),
        ];
    }

    /** @return list<mixed> */
    public static function information(): array
    {
        $pair = static fn (string $key, string $label, bool $area = false): array => [
            ($area ? Textarea::make("{$key}.ar")->rows(2) : TextInput::make("{$key}.ar"))->label($label.' (AR)')->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar']),
            ($area ? Textarea::make("{$key}.en")->rows(2) : TextInput::make("{$key}.en"))->label($label.' (EN)')->extraInputAttributes(['dir' => 'ltr', 'lang' => 'en']),
        ];

        return [
            Repeater::make('faqs')->label(__('courses.faqs'))->reorderable()->addActionLabel(__('courses.add_faq'))->maxItems(30)->defaultItems(0)
                ->schema([Grid::make(['default' => 1, 'md' => 2])->schema([
                    ...$pair('question', __('courses.question')),
                    ...$pair('answer', __('courses.answer'), true),
                ])]),
            Repeater::make('requirements')->label(__('courses.requirements'))->reorderable()->addActionLabel(__('courses.add_requirement'))->maxItems(30)->defaultItems(0)
                ->schema([Grid::make(['default' => 1, 'md' => 2])->schema($pair('text', __('courses.requirement')))]),
            Repeater::make('outcomes')->label(__('courses.outcomes'))->reorderable()->addActionLabel(__('courses.add_outcome'))->maxItems(30)->defaultItems(0)
                ->schema([Grid::make(['default' => 1, 'md' => 2])->schema($pair('text', __('courses.outcome')))]),
        ];
    }

    /** @return array<int, string> */
    public static function categoryOptions(): array
    {
        $options = [];
        foreach (Category::query()->whereNull('parent_id')->orderBy('sort')->with(['children' => static fn ($query) => $query->orderBy('sort')])->get() as $parent) {
            $options[$parent->id] = $parent->name;
            foreach ($parent->children as $child) {
                $options[$child->id] = $parent->name.' › '.$child->name;
            }
        }

        return $options;
    }

    /**
     * @param  list<string>  $values
     * @return array<string, string>
     */
    private static function labelled(string $group, array $values, string $prefix = 'courses.'): array
    {
        $labels = [];
        foreach ($values as $value) {
            $labels[$value] = $group === 'statuses' ? __($prefix.$value) : __("courses.{$group}.{$value}");
        }

        return $labels;
    }
}
