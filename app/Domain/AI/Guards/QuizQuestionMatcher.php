<?php

declare(strict_types=1);

namespace App\Domain\AI\Guards;

use App\Domain\AI\Assistant\TextNormalizer;
use App\Domain\Assessment\Queries\QuizGuardQuestions;
use App\Domain\Assessment\QuizGuard;
use App\Models\LessonCurriculumDocument;
use App\Models\LessonDocumentPage;
use App\Models\User;

/**
 * AI spec 8.3: while the learner has an open quiz attempt, a question that matches one of that quiz's questions is not
 * answered. Matching is text similarity on normalised words only; the answer key is never read. QuizGuard says whether an
 * attempt is open, QuizGuardQuestions gives the prompt text.
 */
final class QuizQuestionMatcher
{
    public function matches(User $learner, string $question, int $courseId): bool
    {
        foreach (QuizGuard::openAttempts($learner, $courseId) as $attempt) {
            $prompts = [];
            foreach ($attempt->snapshot['questions'] ?? [] as $entry) {
                foreach ((array) ($entry['prompt'] ?? []) as $prompt) {
                    if (is_string($prompt)) {
                        $prompts[] = $prompt;
                    }
                }
            }
            if ($this->matchesPrompts($question, $prompts)
                || ($attempt->quiz !== null && $this->matchesPrompts($question, QuizGuardQuestions::promptsFor($attempt->quiz)))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the question is close to one of the given quiz prompts (the same similarity rule, without reading an attempt).
     *
     * @param  list<string>  $prompts
     */
    public function matchesPrompts(string $question, array $prompts): bool
    {
        $asked = TextNormalizer::words($question);
        if ($asked === []) {
            return false;
        }

        foreach ($prompts as $prompt) {
            if ($this->similar($asked, TextNormalizer::words($prompt))) {
                return true;
            }
        }

        return false;
    }

    /** The lesson page that best covers the question, so the hint can say where to look. Null when no page is close enough. */
    public function pageHint(LessonCurriculumDocument $document, string $question): ?int
    {
        $pages = [];
        foreach (LessonDocumentPage::query()->where('document_id', $document->getKey())->orderBy('page_no')->get(['page_no', 'approved_text']) as $page) {
            $pages[$page->page_no] = (string) $page->approved_text;
        }

        return $this->pageHintFor($pages, $question);
    }

    /**
     * @param  array<int, string>  $pages  page number => approved text
     */
    public function pageHintFor(array $pages, string $question): ?int
    {
        $asked = array_values(array_unique(array_filter(TextNormalizer::words($question), static fn (string $word): bool => mb_strlen($word) > 2)));
        if ($asked === []) {
            return null;
        }

        $best = null;
        $bestScore = 0.0;
        foreach ($pages as $number => $text) {
            $score = count(array_intersect($asked, TextNormalizer::words($text))) / count($asked);
            if ($score > $bestScore) {
                $best = $number;
                $bestScore = $score;
            }
        }

        return $bestScore >= (float) config('ai.guards.page_hint_min_overlap', 0.25) ? $best : null;
    }

    /**
     * @param  list<string>  $asked
     * @param  list<string>  $prompt
     */
    private function similar(array $asked, array $prompt): bool
    {
        if ($prompt === []) {
            return false;
        }

        $a = array_unique($asked);
        $p = array_unique($prompt);
        $shared = count(array_intersect($a, $p));
        $dice = 2 * $shared / (count($a) + count($p));
        if ($dice >= (float) config('ai.guards.quiz_similarity', 0.7)) {
            return true;
        }

        return count($p) >= (int) config('ai.guards.quiz_min_words', 4)
            && $shared / count($p) >= (float) config('ai.guards.quiz_containment', 0.8);
    }
}
