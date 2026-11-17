<?php

declare(strict_types=1);

namespace App\Domain\AI\Queries;

use App\Domain\AI\Assistant\AssistantOutcome;
use App\Domain\AI\Assistant\TextNormalizer;
use App\Models\AiConversation;
use App\Models\AiFeedback;
use App\Models\AiMessage;
use App\Models\Lesson;
use App\Models\LessonCurriculumDocument;
use App\Models\LessonKeyTerm;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * What learners ask about one lesson, aggregated and anonymized for its instructor (AI spec 14): most asked topics (the lesson's
 * approved key terms found in questions), most cited pages, the questions the PDF did not cover (the signal for gaps), and how
 * often an answer was not understood. It never returns a student, a time, a thread or an answer. Question texts are the
 * already-masked ones, grouped by normalized wording, and any question that carried personal data is left out entirely.
 */
final class LessonInsights
{
    private const int MAX_MESSAGES = 2000;

    private const int MAX_GAPS = 20;

    /**
     * @return array{
     *     questions: int, answered: int, not_in_source: int, out_of_scope: int, fallback: int, not_understood: int, simpler: int,
     *     topics: list<array{term: string, count: int}>, pages: list<array{page: int, count: int}>, gaps: list<array{question: string, count: int}>
     * }
     */
    public function for(Lesson $lesson, CarbonInterface $from, CarbonInterface $to): array
    {
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();
        $messages = static fn (): Builder => AiMessage::query()
            ->whereIn('conversation_id', AiConversation::query()->where('lesson_id', $lesson->getKey())->select('id'))
            ->whereBetween('created_at', [$start, $end]);

        $assistant = $messages()->where('role', AiMessage::ROLE_ASSISTANT);
        $count = static fn (string $status): int => (clone $assistant)->where('status', $status)->count();

        $users = $messages()->where('role', AiMessage::ROLE_USER)->orderByDesc('id')->limit(self::MAX_MESSAGES)->get(['id', 'conversation_id', 'content', 'guard_flags']);
        $asked = $users->filter(fn (AiMessage $message): bool => $this->isPlainQuestion($message));

        $pages = [];
        foreach ((clone $assistant)->where('status', AssistantOutcome::Answered->value)->limit(self::MAX_MESSAGES)->pluck('source_pages') as $cited) {
            foreach (is_array($cited) ? $cited : (array) json_decode((string) $cited, true) as $page) {
                $pages[(int) $page] = ($pages[(int) $page] ?? 0) + 1;
            }
        }
        arsort($pages);

        return [
            'questions' => $users->filter(static fn (AiMessage $message): bool => ($message->guard_flags['mode'] ?? 'normal') !== 'simpler')->count(),
            'answered' => $count(AssistantOutcome::Answered->value),
            'not_in_source' => $count(AssistantOutcome::NotInSource->value),
            'out_of_scope' => $count(AssistantOutcome::OutOfScope->value),
            'fallback' => $count(AssistantOutcome::Fallback->value),
            'not_understood' => AiFeedback::query()->where('type', AiFeedback::NOT_UNDERSTOOD)->whereBetween('created_at', [$start, $end])
                ->whereIn('message_id', AiMessage::query()->whereIn('conversation_id', AiConversation::query()->where('lesson_id', $lesson->getKey())->select('id'))->select('id'))->count(),
            'simpler' => $users->filter(static fn (AiMessage $message): bool => ($message->guard_flags['mode'] ?? null) === 'simpler')->count(),
            'topics' => $this->topics($lesson, $asked->pluck('content')->all()),
            'pages' => array_map(static fn (int $page, int $count): array => ['page' => $page, 'count' => $count], array_keys(array_slice($pages, 0, 10, true)), array_values(array_slice($pages, 0, 10, true))),
            'gaps' => $this->gaps($lesson, $start, $end),
        ];
    }

    /**
     * @param  list<string>  $questions
     * @return list<array{term: string, count: int}>
     */
    private function topics(Lesson $lesson, array $questions): array
    {
        $documents = LessonCurriculumDocument::query()->where('lesson_id', $lesson->getKey())->select('id');
        $terms = LessonKeyTerm::query()->whereIn('document_id', $documents)->where('approved', true)->pluck('term')->unique();
        $normalized = array_map(TextNormalizer::normalize(...), $questions);

        $found = [];
        foreach ($terms as $term) {
            $needle = TextNormalizer::normalize((string) $term);
            if ($needle === '') {
                continue;
            }
            $hits = count(array_filter($normalized, static fn (string $question): bool => str_contains($question, $needle)));
            if ($hits > 0) {
                $found[] = ['term' => (string) $term, 'count' => $hits];
            }
        }
        usort($found, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return array_slice($found, 0, 10);
    }

    /**
     * Questions the lesson PDF did not cover, grouped by wording: each answer marked not_in_source is paired with the question
     * that precedes it in the same thread.
     *
     * @return list<array{question: string, count: int}>
     */
    private function gaps(Lesson $lesson, CarbonInterface $start, CarbonInterface $end): array
    {
        $answers = AiMessage::query()
            ->whereIn('conversation_id', AiConversation::query()->where('lesson_id', $lesson->getKey())->select('id'))
            ->where('role', AiMessage::ROLE_ASSISTANT)->where('status', AssistantOutcome::NotInSource->value)
            ->whereBetween('created_at', [$start, $end])->orderByDesc('id')->limit(300)->get(['id', 'conversation_id']);
        if ($answers->isEmpty()) {
            return [];
        }

        $questions = AiMessage::query()->where('role', AiMessage::ROLE_USER)->whereIn('conversation_id', $answers->pluck('conversation_id'))
            ->orderBy('id')->get(['id', 'conversation_id', 'content', 'guard_flags'])->groupBy('conversation_id');

        $groups = [];
        foreach ($answers as $answer) {
            $question = $questions->get($answer->conversation_id)?->filter(static fn (AiMessage $row): bool => $row->getKey() < $answer->getKey())->last();
            if (! $question instanceof AiMessage || ! $this->isPlainQuestion($question)) {
                continue;
            }

            $key = TextNormalizer::normalize($question->content);
            if ($key === '') {
                continue;
            }
            $groups[$key] ??= ['question' => mb_strimwidth(trim($question->content), 0, 140, '…'), 'count' => 0];
            $groups[$key]['count']++;
        }

        $list = array_values($groups);
        usort($list, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return array_slice($list, 0, self::MAX_GAPS);
    }

    /** A question counts only when it is its own wording and carried no personal data (the stored text is masked, this is a second guard). */
    private function isPlainQuestion(AiMessage $message): bool
    {
        $flags = $message->guard_flags ?? [];

        return ($flags['mode'] ?? 'normal') !== 'simpler' && empty($flags['pii']) && trim($message->content) !== '';
    }
}
