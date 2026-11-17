<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\AI\Assistant\AssistantRefused;
use App\Domain\AI\Assistant\StyleTier;
use App\Domain\AI\Console\Actions\AskInTestConsole;
use App\Domain\AI\Console\TestConsoleAccess;
use App\Domain\AI\Contracts\AiProviderInterface;
use App\Domain\AI\Sandbox\SandboxResult;
use App\Models\User;
use Filament\Pages\Page;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * T-P7-05, AI spec 13.5: the instructor asks the assistant about a lesson's own approved document, as a learner would, before
 * enabling it. Everything happens in the sandbox action: nothing is stored that learner usage, quotas or analytics read.
 * The page is clearly labelled as test mode.
 */
final class AssistantTestConsole extends Page
{
    protected static ?string $slug = 'assistant-test-console';

    protected string $view = 'filament.ai.test-console';

    public ?int $lessonId = null;

    public string $question = '';

    public string $locale = 'en';

    public string $tier = 'adults';

    /** @var array<string, mixed>|null */
    public ?array $result = null;

    public ?string $error = null;

    public static function getNavigationLabel(): string
    {
        return __('ai.console.nav');
    }

    public static function getNavigationGroup(): string
    {
        return __('ai_admin.group');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-beaker';
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->getAttribute('status') === 'active' && $user->can('lesson_document.upload');
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);
        $this->locale = app()->getLocale() === 'ar' ? 'ar' : 'en';
    }

    #[Unexposed]
    public function getTitle(): string
    {
        return __('ai.console.title');
    }

    /** @return array<string, string> */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function ask(): void
    {
        $user = $this->actor();
        $this->result = null;
        $this->error = null;

        $lesson = TestConsoleAccess::lessonsFor($user)->firstWhere('id', $this->lessonId);
        if ($lesson === null) {
            $this->error = __('ai.console.pick_lesson');

            return;
        }

        try {
            $result = app(AskInTestConsole::class)($user, $lesson, $this->question, $this->locale, StyleTier::tryFrom($this->tier) ?? StyleTier::Adults);
        } catch (AuthorizationException) {
            abort(403);
        } catch (ValidationException $exception) {
            $this->error = (string) $exception->validator->errors()->first();

            return;
        } catch (AssistantRefused $exception) {
            $this->error = $exception->getMessage();

            return;
        }

        $this->result = $this->present($result);
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $lessons = [];
        foreach (TestConsoleAccess::lessonsFor($this->actor()) as $lesson) {
            $lessons[$lesson->getKey()] = $lesson->item?->course?->title.' · '.$lesson->title;
        }

        return [
            'lessons' => $lessons,
            'maxChars' => (int) config('ai.question_max_chars', 300),
            'provider' => class_basename(app(AiProviderInterface::class)),
        ];
    }

    /** @return array<string, mixed> */
    private function present(SandboxResult $result): array
    {
        return [
            'outcome' => $result->outcome->value,
            'message' => $result->message,
            'pages' => $result->pages,
            'quote' => $result->quote,
            'quote_verified' => $result->quoteVerified,
            'language' => $result->language,
            'masked_question' => $result->maskedQuestion,
            'pii' => array_keys($result->piiKinds),
            'input_guard' => $result->inputGuard?->value,
            'output_failure' => $result->outputFailure?->value,
            'provider_failure' => $result->providerFailure,
            'provider_called' => $result->providerCalled(),
            'attempts' => $result->attempts,
            'trimmed' => $result->trimmed,
            'tier' => $result->tier->value,
        ];
    }

    private function actor(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
