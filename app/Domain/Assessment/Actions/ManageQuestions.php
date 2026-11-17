<?php

declare(strict_types=1);

namespace App\Domain\Assessment\Actions;

use App\Domain\Assessment\QuestionRules;
use App\Domain\Assessment\QuizAccess;
use App\Domain\Audit\AuditLogger;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Add, change, deactivate and order the questions of a quiz. A removed question is only deactivated, so attempts that
 * already used it keep their history; any change that can alter grading raises the question version (T-P6-02 snapshots it).
 */
final class ManageQuestions
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data see QuestionRules::clean */
    public function add(User $actor, Quiz $quiz, array $data): Question
    {
        QuizAccess::authorize($actor, $quiz);
        $clean = QuestionRules::clean($data);

        return DB::transaction(function () use ($actor, $quiz, $clean): Question {
            Quiz::query()->whereKey($quiz->getKey())->lockForUpdate()->first();
            $sort = (int) Question::query()->where('quiz_id', $quiz->getKey())->max('sort') + 1;
            $question = Question::query()->create([
                'quiz_id' => $quiz->getKey(), 'type' => $clean['type'], 'prompt' => $clean['prompt'], 'explanation' => $clean['explanation'] === [] ? null : $clean['explanation'],
                'points' => $clean['points'], 'sort' => $sort, 'active' => true, 'version' => 1,
            ]);
            $this->writeOptions($question, $clean['options']);
            $this->log('quiz.question_added', $question, $actor, null, ['type' => $clean['type'], 'points' => $clean['points']]);

            return $question->load('options');
        });
    }

    /** @param array<string, mixed> $data see QuestionRules::clean */
    public function update(User $actor, Question $question, array $data): Question
    {
        QuizAccess::authorize($actor, $question->quiz);
        $clean = QuestionRules::clean($data);

        return DB::transaction(function () use ($actor, $question, $clean): Question {
            $locked = Question::query()->whereKey($question->getKey())->lockForUpdate()->firstOrFail();
            $before = $this->shape($locked);
            $locked->update([
                'type' => $clean['type'], 'prompt' => $clean['prompt'], 'explanation' => $clean['explanation'] === [] ? null : $clean['explanation'], 'points' => $clean['points'],
            ]);
            $locked->options()->delete();
            $this->writeOptions($locked, $clean['options']);
            $locked->unsetRelation('options');

            $after = $this->shape($locked->refresh());
            if ($after !== $before) {
                $locked->increment('version');
                $this->log('quiz.question_changed', $locked, $actor, $before, $after);
            }

            return $locked->refresh()->load('options');
        });
    }

    public function setActive(User $actor, Question $question, bool $active): void
    {
        QuizAccess::authorize($actor, $question->quiz);
        if ($question->active === $active) {
            return;
        }
        $question->update(['active' => $active]);
        $this->log($active ? 'quiz.question_restored' : 'quiz.question_deactivated', $question, $actor, null, ['active' => $active]);
    }

    /** Moves one question up or down among the quiz questions. */
    public function move(User $actor, Question $question, string $direction): void
    {
        QuizAccess::authorize($actor, $question->quiz);
        if (! in_array($direction, ['up', 'down'], true)) {
            throw ValidationException::withMessages(['direction' => __('quiz.errors.direction')]);
        }

        DB::transaction(function () use ($question, $direction): void {
            Quiz::query()->whereKey($question->quiz_id)->lockForUpdate()->first();
            $ids = Question::query()->where('quiz_id', $question->quiz_id)->orderBy('sort')->orderBy('id')->pluck('id')->all();
            $index = array_search($question->getKey(), $ids, true);
            $swap = $direction === 'up' ? $index - 1 : $index + 1;
            if ($index === false || ! isset($ids[$swap])) {
                return;
            }
            [$ids[$index], $ids[$swap]] = [$ids[$swap], $ids[$index]];
            foreach ($ids as $position => $id) {
                Question::query()->whereKey($id)->update(['sort' => $position + 1]);
            }
        });
    }

    /** @param list<array{text: array<string, string>, is_correct: bool}> $options */
    private function writeOptions(Question $question, array $options): void
    {
        foreach ($options as $position => $option) {
            QuestionOption::query()->create(['question_id' => $question->getKey(), 'text' => $option['text'], 'is_correct' => $option['is_correct'], 'sort' => $position + 1]);
        }
    }

    /** @return array<string, mixed> everything that can change how the question is shown or graded */
    private function shape(Question $question): array
    {
        return [
            'type' => $question->type, 'points' => (string) $question->points, 'prompt' => $question->getTranslations('prompt'), 'explanation' => $question->getTranslations('explanation'),
            'options' => $question->options()->get()->map(fn (QuestionOption $o): array => ['text' => $o->getTranslations('text'), 'is_correct' => $o->is_correct])->all(),
        ];
    }

    /**
     * @param  array<array-key, mixed>|null  $before
     * @param  array<array-key, mixed>  $after
     */
    private function log(string $event, Question $question, User $actor, ?array $before, array $after): void
    {
        if ($this->audit->record($event, $question, (int) $actor->getKey(), $before, $after) === null) {
            throw new RuntimeException('Unable to record quiz audit.');
        }
    }
}
