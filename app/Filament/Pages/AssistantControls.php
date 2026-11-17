<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\AI\Admin\Actions\DisableAssistantGlobally;
use App\Domain\AI\Admin\Actions\EnableAssistantGlobally;
use App\Domain\AI\Admin\Actions\ResetProviderCircuit;
use App\Domain\AI\Admin\Actions\SaveAssistantProvider;
use App\Domain\AI\Admin\Actions\SaveAssistantQuotas;
use App\Domain\AI\Admin\Actions\SaveAssistantRetention;
use App\Domain\AI\Admin\AssistantAdminAccess;
use App\Domain\AI\Queries\AssistantReadiness;
use App\Domain\AI\Queries\ProviderHealthReport;
use App\Domain\AI\Resilience\ProviderRegistry;
use App\Domain\Settings\AiSettings;
use App\Models\AiPromptVersion;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * T-P7-06, AI spec 14: the operator console of the lesson assistant. A super admin (ai.configure) sees the go-live checklist and
 * the provider health, flips the global switch, picks provider, model, fallback and prompt version, and sets quotas, budgets and
 * retention. All writes go through Domain actions that authorize again and audit; this page only collects input.
 */
final class AssistantControls extends Page
{
    protected static ?string $slug = 'assistant-controls';

    protected string $view = 'filament.ai.controls';

    public static function getNavigationLabel(): string
    {
        return __('ai_admin.controls.nav');
    }

    public static function getNavigationGroup(): string
    {
        return __('ai_admin.group');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-cog-6-tooth';
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && AssistantAdminAccess::canConfigure($user);
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);
    }

    #[Unexposed]
    public function getTitle(): string
    {
        return __('ai_admin.controls.title');
    }

    /** @return array<string, string> */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function enableAction(): Action
    {
        return Action::make('enable')->label(__('ai_admin.controls.enable'))->modalHeading(__('ai_admin.controls.enable'))->modalDescription(__('ai_admin.controls.enable_help'))
            ->schema([TextInput::make('approval_reference')->label(__('ai_admin.fields.approval_reference'))->required()->maxLength(255)])
            ->action(fn (array $data, Action $action) => $this->attempt($action, static fn (User $user) => app(EnableAssistantGlobally::class)($user, (string) $data['approval_reference']), 'enabled'));
    }

    public function disableAction(): Action
    {
        return Action::make('disable')->label(__('ai_admin.controls.disable'))->color('danger')->requiresConfirmation()
            ->modalHeading(__('ai_admin.controls.disable'))->modalDescription(__('ai_admin.controls.disable_help'))
            ->action(fn (Action $action) => $this->attempt($action, static fn (User $user) => app(DisableAssistantGlobally::class)($user), 'disabled'));
    }

    public function providerAction(): Action
    {
        $keys = $this->providerOptions();

        return Action::make('provider')->label(__('ai_admin.controls.edit'))->modalHeading(__('ai_admin.controls.provider_title'))->modalDescription(__('ai_admin.controls.provider_help'))
            ->schema([
                Select::make('provider')->label(__('ai_admin.fields.provider'))->options($keys)->required()->native(false),
                Select::make('fallback_provider')->label(__('ai_admin.fields.fallback_provider'))->options($keys)->native(false)->placeholder(__('ai_admin.controls.none')),
                TextInput::make('model')->label(__('ai_admin.fields.model'))->maxLength(80),
                Select::make('prompt_version')->label(__('ai_admin.fields.prompt_version'))->options($this->promptOptions())->required()->native(false),
                TextInput::make('breaker_failures')->label(__('ai_admin.fields.breaker_failures'))->numeric()->minValue(1)->maxValue(100)->required(),
                TextInput::make('breaker_window_seconds')->label(__('ai_admin.fields.breaker_window_seconds'))->numeric()->minValue(10)->maxValue(3600)->required(),
                TextInput::make('breaker_cool_off_seconds')->label(__('ai_admin.fields.breaker_cool_off_seconds'))->numeric()->minValue(5)->maxValue(3600)->required(),
            ])
            ->fillForm(function (): array {
                $settings = app(AiSettings::class);
                $registry = app(ProviderRegistry::class);

                return [
                    'provider' => $registry->activeKey(), 'fallback_provider' => $registry->fallbackKey(), 'model' => $settings->model,
                    'prompt_version' => AiPromptVersion::current()?->version,
                    'breaker_failures' => $settings->breaker_failures, 'breaker_window_seconds' => $settings->breaker_window_seconds, 'breaker_cool_off_seconds' => $settings->breaker_cool_off_seconds,
                ];
            })
            ->action(fn (array $data, Action $action) => $this->attempt($action, static fn (User $user) => app(SaveAssistantProvider::class)($user, $data), 'saved'));
    }

    public function quotasAction(): Action
    {
        return Action::make('quotas')->label(__('ai_admin.controls.edit'))->modalHeading(__('ai_admin.controls.quotas_title'))->modalDescription(__('ai_admin.controls.quotas_help'))
            ->schema([
                TextInput::make('questions_per_student_day')->label(__('ai_admin.fields.questions_per_student_day'))->numeric()->minValue(1)->maxValue(1000)->required(),
                TextInput::make('questions_per_student_lesson_day')->label(__('ai_admin.fields.questions_per_student_lesson_day'))->numeric()->minValue(1)->maxValue(1000)->required(),
                TextInput::make('monthly_budget_minor')->label(__('ai_admin.fields.monthly_budget_minor'))->helperText(__('ai_admin.controls.budget_help'))->numeric()->minValue(0),
                TextInput::make('course_monthly_budget_minor')->label(__('ai_admin.fields.course_monthly_budget_minor'))->numeric()->minValue(0),
                TextInput::make('budget_currency')->label(__('ai_admin.fields.budget_currency'))->maxLength(3),
                TextInput::make('budget_alert_percent')->label(__('ai_admin.fields.budget_alert_percent'))->numeric()->minValue(1)->maxValue(100)->required(),
            ])
            ->fillForm(fn (): array => $this->quotaValues())
            ->action(fn (array $data, Action $action) => $this->attempt($action, static fn (User $user) => app(SaveAssistantQuotas::class)($user, $data), 'saved'));
    }

    public function retentionAction(): Action
    {
        return Action::make('retention')->label(__('ai_admin.controls.edit'))->modalHeading(__('ai_admin.controls.retention_title'))->modalDescription(__('ai_admin.controls.retention_help'))
            ->schema([
                TextInput::make('message_retention_days')->label(__('ai_admin.fields.message_retention_days'))->numeric()->minValue(7)->maxValue(1095)->required(),
                TextInput::make('usage_retention_months')->label(__('ai_admin.fields.usage_retention_months'))->numeric()->minValue(1)->maxValue(60)->required(),
            ])
            ->fillForm(fn (): array => ['message_retention_days' => app(AiSettings::class)->message_retention_days, 'usage_retention_months' => app(AiSettings::class)->usage_retention_months])
            ->action(fn (array $data, Action $action) => $this->attempt($action, static fn (User $user) => app(SaveAssistantRetention::class)($user, $data), 'saved'));
    }

    public function resetCircuitAction(): Action
    {
        return Action::make('resetCircuit')->label(__('ai_admin.controls.reset_circuit'))->requiresConfirmation()
            ->modalHeading(__('ai_admin.controls.reset_circuit'))->modalDescription(__('ai_admin.controls.reset_circuit_help'))
            ->action(fn (array $arguments, Action $action) => $this->attempt($action, static fn (User $user) => app(ResetProviderCircuit::class)($user, (string) ($arguments['route'] ?? '')), 'circuit_reset'));
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $settings = app(AiSettings::class);

        return [
            'on' => EnableAssistantGlobally::isOn(),
            'readiness' => app(AssistantReadiness::class)(),
            'health' => app(ProviderHealthReport::class)(),
            'settings' => $settings,
            'prompt' => AiPromptVersion::current()?->version,
            'fallback' => app(ProviderRegistry::class)->fallbackKey(),
            'provider' => app(ProviderRegistry::class)->activeKey(),
        ];
    }

    /** @return array<string, int|string|null> */
    private function quotaValues(): array
    {
        $settings = app(AiSettings::class);

        return [
            'questions_per_student_day' => $settings->questions_per_student_day, 'questions_per_student_lesson_day' => $settings->questions_per_student_lesson_day,
            'monthly_budget_minor' => $settings->monthly_budget_minor, 'course_monthly_budget_minor' => $settings->course_monthly_budget_minor,
            'budget_currency' => $settings->budget_currency, 'budget_alert_percent' => $settings->budget_alert_percent,
        ];
    }

    /** @return array<string, string> */
    private function providerOptions(): array
    {
        $options = [];
        foreach (app(ProviderRegistry::class)->keys() as $key) {
            $options[$key] = __('ai_admin.providers.'.$key);
        }

        return $options;
    }

    /** @return array<int, string> */
    private function promptOptions(): array
    {
        return AiPromptVersion::query()->orderByDesc('version')->pluck('version')->mapWithKeys(static fn (mixed $version): array => [(int) $version => __('ai_admin.controls.prompt_version', ['version' => $version])])->all();
    }

    /** @param  callable(User): mixed  $run */
    private function attempt(Action $action, callable $run, string $done): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        try {
            $run($user);
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();
            $action->halt();

            return;
        } catch (AuthorizationException) {
            Notification::make()->danger()->title(__('ai_admin.errors.denied'))->send();
            $action->halt();

            return;
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->danger()->title(__('ai_admin.errors.failed'))->send();
            $action->halt();

            return;
        }

        Notification::make()->success()->title(__('ai_admin.controls.'.$done))->send();
    }
}
