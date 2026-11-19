<?php

declare(strict_types=1);

namespace App\Filament\Resources\Courses\Pages;

use App\Domain\Assessment\Actions\ConfigureQuiz;
use App\Domain\Assessment\Actions\ManageQuestions;
use App\Domain\Assessment\QuizAccess;
use App\Domain\Assessment\QuizPassMark;
use App\Filament\Resources\Courses\CourseResource;
use App\Models\Course;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

/** T-P6-01: author one quiz of a course: settings, questions per type, order, and a learner-style preview that never shows the answer key. */
final class ManageQuiz extends Page
{
    use InteractsWithRecord;

    protected static string $resource = CourseResource::class;

    protected string $view = 'filament.courses.manage-quiz';

    #[Locked]
    public int $quizId = 0;

    public bool $previewing = false;

    public function mount(int|string $record, int|string $quiz): void
    {
        $this->record = $this->resolveRecord($record);
        $this->quizId = (int) $quiz;
        $this->quiz();
    }

    public function getTitle(): string
    {
        return (string) $this->quiz()->getTranslation('title', app()->getLocale());
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->addQuestionAction(),
            $this->settingsAction(),
            Action::make('preview')->label(__('quiz.preview'))->icon('heroicon-o-eye')->color('gray')->action(function (): void {
                $this->previewing = ! $this->previewing;
            }),
        ];
    }

    public function settingsAction(): Action
    {
        return Action::make('settings')->label(__('quiz.settings'))->icon('heroicon-o-cog-6-tooth')->color('gray')->modalHeading(__('quiz.settings'))
            ->schema([
                TextInput::make('time_limit_minutes')->label(__('quiz.time_limit'))->helperText(__('quiz.time_limit_help'))->numeric()->minValue(1)->maxValue(1440),
                TextInput::make('pass_mark_percent')->label(__('quiz.pass_mark'))->helperText(__('quiz.pass_mark_help', ['percent' => QuizPassMark::inherited($this->quiz())]))->placeholder((string) QuizPassMark::inherited($this->quiz()))->numeric()->minValue(1)->maxValue(100),
                TextInput::make('max_attempts')->label(__('quiz.max_attempts'))->helperText(__('quiz.max_attempts_help'))->numeric()->minValue(1)->maxValue(100),
                Select::make('result_visibility')->label(__('quiz.result_visibility'))->native(false)->required()
                    ->options(collect(Quiz::RESULT_VISIBILITY)->mapWithKeys(fn (string $v): array => [$v => __('quiz.visibility.'.$v)])->all()),
                Toggle::make('randomize')->label(__('quiz.randomize'))->helperText(__('quiz.randomize_help')),
                Select::make('progression')->label(__('quiz.progression'))->native(false)->required()
                    ->options(collect(Quiz::PROGRESSION)->mapWithKeys(fn (string $v): array => [$v => __('quiz.progressions.'.$v)])->all()),
            ])
            ->fillForm(fn (): array => $this->quiz()->only(['time_limit_minutes', 'pass_mark_percent', 'max_attempts', 'result_visibility', 'randomize', 'progression']))
            ->action(function (array $data, Action $action): void {
                if (! $this->attempt($action, fn () => app(ConfigureQuiz::class)($this->actor(), $this->quiz(), $data))) {
                    return;
                }
                Notification::make()->success()->title(__('quiz.saved'))->send();
            });
    }

    public function addQuestionAction(): Action
    {
        return Action::make('addQuestion')->label(__('quiz.add_question'))->icon('heroicon-o-plus')->modalHeading(__('quiz.add_question'))->modalWidth('3xl')
            ->schema($this->questionSchema())->fillForm(fn (): array => ['type' => Question::SINGLE, 'points' => 1])
            ->action(function (array $data, Action $action): void {
                if (! $this->attempt($action, fn () => app(ManageQuestions::class)->add($this->actor(), $this->quiz(), $this->questionData($data)))) {
                    return;
                }
                Notification::make()->success()->title(__('quiz.question_saved'))->send();
            });
    }

    public function editQuestionAction(): Action
    {
        return Action::make('editQuestion')->label(__('quiz.edit'))->modalHeading(__('quiz.edit'))->modalWidth('3xl')
            ->schema($this->questionSchema())
            ->fillForm(function (array $arguments): array {
                $question = $this->questionFor($arguments['question'] ?? 0);

                return [
                    'type' => $question->type, 'points' => (float) $question->points, 'prompt' => $question->getTranslations('prompt'), 'explanation' => $question->getTranslations('explanation'),
                    'options' => $question->type === Question::TRUE_FALSE ? [] : $question->options->map(fn ($o): array => ['text' => $o->getTranslations('text'), 'is_correct' => $o->is_correct])->all(),
                    'true_is_correct' => $question->type === Question::TRUE_FALSE ? (bool) ($question->options->first()?->is_correct) : null,
                ];
            })
            ->action(function (array $data, array $arguments, Action $action): void {
                $question = $this->questionFor($arguments['question'] ?? 0);
                if (! $this->attempt($action, fn () => app(ManageQuestions::class)->update($this->actor(), $question, $this->questionData($data)))) {
                    return;
                }
                Notification::make()->success()->title(__('quiz.question_saved'))->send();
            });
    }

    public function moveQuestion(int $questionId, string $direction): void
    {
        app(ManageQuestions::class)->move($this->actor(), $this->questionFor($questionId), $direction);
    }

    public function toggleQuestion(int $questionId): void
    {
        $question = $this->questionFor($questionId);
        app(ManageQuestions::class)->setActive($this->actor(), $question, ! $question->active);
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $quiz = $this->quiz();
        $questions = $quiz->questions()->with('options')->get();

        return [
            'quiz' => $quiz,
            'course' => $this->course(),
            'questions' => $questions,
            'totalMarks' => (float) $questions->where('active', true)->sum('points'),
        ];
    }

    /** @return list<mixed> */
    private function questionSchema(): array
    {
        $typeOptions = collect(Question::TYPES)->mapWithKeys(fn (string $t): array => [$t => __('quiz.types.'.$t)])->all();

        return [
            Select::make('type')->label(__('quiz.type'))->options($typeOptions)->native(false)->required()->live(),
            Textarea::make('prompt.ar')->label(__('quiz.prompt').' (AR)')->rows(3)->maxLength(5000)->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar']),
            Textarea::make('prompt.en')->label(__('quiz.prompt').' (EN)')->rows(3)->maxLength(5000)->extraInputAttributes(['dir' => 'ltr', 'lang' => 'en']),
            TextInput::make('points')->label(__('quiz.points'))->numeric()->minValue(0.25)->maxValue(1000)->step(0.25)->required(),
            Toggle::make('true_is_correct')->label(__('quiz.true_is_correct'))->helperText(__('quiz.true_is_correct_help'))->visible(fn (Get $get): bool => $get('type') === Question::TRUE_FALSE),
            Repeater::make('options')->label(fn (Get $get): string => $get('type') === Question::SHORT_ANSWER ? __('quiz.accepted_answers') : __('quiz.options'))
                ->visible(fn (Get $get): bool => in_array($get('type'), [Question::SINGLE, Question::MULTIPLE, Question::SHORT_ANSWER], true))
                ->schema([
                    TextInput::make('text.ar')->label(__('quiz.option_text').' (AR)')->maxLength(1000)->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar']),
                    TextInput::make('text.en')->label(__('quiz.option_text').' (EN)')->maxLength(1000)->extraInputAttributes(['dir' => 'ltr', 'lang' => 'en']),
                    Toggle::make('is_correct')->label(__('quiz.is_correct'))->visible(fn (Get $get): bool => in_array($get('../../type'), [Question::SINGLE, Question::MULTIPLE], true)),
                ])->columns(2)->maxItems(8)->defaultItems(2)->reorderable()->addActionLabel(__('quiz.add_option')),
            Textarea::make('explanation.ar')->label(__('quiz.explanation').' (AR)')->rows(2)->maxLength(5000)->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar']),
            Textarea::make('explanation.en')->label(__('quiz.explanation').' (EN)')->rows(2)->maxLength(5000)->extraInputAttributes(['dir' => 'ltr', 'lang' => 'en']),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function questionData(array $data): array
    {
        $data['options'] = array_values((array) ($data['options'] ?? []));
        if (($data['type'] ?? null) === Question::SHORT_ANSWER) {
            $data['options'] = array_map(fn ($option): array => [...(array) $option, 'is_correct' => true], $data['options']);
        }

        return $data;
    }

    /** Runs the save; a refused input is shown to the author and keeps the dialog open. */
    private function attempt(Action $action, \Closure $save): bool
    {
        try {
            $save();

            return true;
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();
            $action->halt();

            return false;
        }
    }

    private function quiz(): Quiz
    {
        $quiz = Quiz::query()->whereHas('item', fn (Builder $item): Builder => $item->where('course_id', $this->course()->getKey()))->findOrFail($this->quizId);
        QuizAccess::authorize($this->actor(), $quiz);

        return $quiz;
    }

    private function questionFor(int|string $id): Question
    {
        return $this->quiz()->questions()->with(['options', 'quiz'])->findOrFail($id);
    }

    private function course(): Course
    {
        /** @var Course $course */
        $course = $this->record;

        return $course;
    }

    private function actor(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
