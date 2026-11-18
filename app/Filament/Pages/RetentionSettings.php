<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Privacy\Actions\SaveRetentionPolicies;
use App\Domain\Privacy\RetentionClass;
use App\Domain\Privacy\RetentionPolicies;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Throwable;

/** T-P9-08: retention days per data class (O-025 OPEN). The page only collects input; SaveRetentionPolicies authorizes, validates and audits. */
final class RetentionSettings extends Page
{
    protected static ?string $slug = 'retention-policies';

    protected string $view = 'filament.privacy.retention';

    public static function getNavigationLabel(): string
    {
        return __('privacy.retention_nav');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-archive-box-x-mark';
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->getAttribute('status') === 'active' && $user->can('privacy.manage');
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);
    }

    #[Unexposed]
    public function getTitle(): string
    {
        return __('privacy.retention_title');
    }

    /** @return array<string, string> */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function editAction(): Action
    {
        $fields = [];
        foreach (RetentionClass::cases() as $class) {
            if ($class->isManagedElsewhere()) {
                continue;
            }
            $fields[] = TextInput::make($class->value)->label($class->label())->numeric()->minValue(1)->maxValue(3650)
                ->helperText($class === RetentionClass::DataExports ? __('privacy.retention_export_help') : __('privacy.retention_blank_help'));
        }

        return Action::make('edit')->label(__('privacy.retention_edit'))->modalHeading(__('privacy.retention_title'))->modalDescription(__('privacy.retention_intro'))
            ->schema($fields)
            ->fillForm(function (): array {
                $values = [];
                foreach (app(RetentionPolicies::class)->overview() as $key => $row) {
                    $values[$key] = $row['days'];
                }

                return $values;
            })
            ->action(function (array $data, Action $action): void {
                $user = Auth::user();
                abort_unless($user instanceof User, 401);

                try {
                    app(SaveRetentionPolicies::class)($user, $data);
                } catch (ValidationException $exception) {
                    Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();
                    $action->halt();

                    return;
                } catch (AuthorizationException) {
                    Notification::make()->danger()->title(__('privacy.denied'))->send();
                    $action->halt();

                    return;
                } catch (Throwable $exception) {
                    report($exception);
                    Notification::make()->danger()->title(__('privacy.retention_failed'))->send();
                    $action->halt();

                    return;
                }

                Notification::make()->success()->title(__('privacy.retention_saved'))->send();
            });
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return ['rows' => app(RetentionPolicies::class)->overview()];
    }
}
