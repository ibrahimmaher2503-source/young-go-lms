<?php

declare(strict_types=1);

namespace App\Domain\AI\Console\Actions;

use App\Domain\AI\Assistant\AssistantRefused;
use App\Domain\AI\Assistant\ContextBuilder;
use App\Domain\AI\Assistant\StyleTier;
use App\Domain\AI\Console\TestConsoleAccess;
use App\Domain\AI\Sandbox\SandboxResult;
use App\Domain\AI\Sandbox\SandboxRunner;
use App\Domain\AI\UnavailableReason;
use App\Domain\Audit\AuditLogger;
use App\Models\AiPromptVersion;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Instructor test console (AI spec 13.5): ask the assistant about the lesson's own active document the way a learner would,
 * before enabling it. It reads the document and calls the provider, and writes nothing a learner report reads: no
 * conversation, message, usage, quota counter, cache entry or safeguarding flag. The only record is one audit event with ids
 * (never the question or the answer). It does not depend on the assistant being switched on for the lesson.
 */
final class AskInTestConsole
{
    public function __construct(
        private readonly ContextBuilder $contexts,
        private readonly SandboxRunner $sandbox,
        private readonly AuditLogger $audit,
    ) {}

    public function __invoke(User $user, Lesson $lesson, string $question, string $locale, StyleTier $tier): SandboxResult
    {
        $course = $lesson->item?->course;
        if ($course === null || ! TestConsoleAccess::allows($user, $course)) {
            throw new AuthorizationException;
        }

        $document = $lesson->activeCurriculumDocument() ?? throw AssistantRefused::unavailable(UnavailableReason::NoApprovedDocument);
        $prompt = AiPromptVersion::current() ?? throw AssistantRefused::unavailable(UnavailableReason::GlobalOff);

        $question = trim($question);
        $max = (int) config('ai.question_max_chars', 300);
        if ($question === '' || mb_strlen($question) > $max) {
            throw ValidationException::withMessages(['question' => __('ai.question_invalid', ['max' => $max])]);
        }

        $key = 'ai-console:'.$user->getKey();
        if (! RateLimiter::attempt($key, max(1, (int) config('ai.console.per_minute', 10)), static fn (): bool => true, 60)) {
            throw ValidationException::withMessages(['question' => __('ai.quota.rate_limited')]);
        }

        $result = $this->sandbox->run(
            $this->contexts->build($document, $question),
            (string) $lesson->title,
            $user,
            $question,
            in_array($locale, ['ar', 'en'], true) ? $locale : 'en',
            $tier,
            $prompt,
        );

        if ($this->audit->record('ai.console_asked', $lesson, (int) $user->getKey(), null, null, [
            'course_id' => $course->getKey(), 'lesson_id' => $lesson->getKey(), 'document_version' => $document->version,
            'status' => $result->outcome->value, 'guard' => $result->inputGuard?->value, 'tier' => $tier->value, 'sandbox' => true,
        ]) === null) {
            throw new RuntimeException('Unable to record console audit.');
        }

        return $result;
    }
}
