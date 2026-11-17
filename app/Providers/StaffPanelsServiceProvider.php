<?php

declare(strict_types=1);

namespace App\Providers;

use App\Filament\Auth\WorkspaceLogin;
use App\Filament\Pages\WorkspaceDashboard;
use App\Filament\Resources\Audit\AuditLogResource;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\CmsPages\CmsPageResource;
use App\Filament\Resources\Courses\CourseResource;
use App\Filament\Resources\EssayReview\EssayReviewResource;
use App\Filament\Resources\HelpRequests\HelpRequestResource;
use App\Filament\Resources\HomepageBlocks\HomepageBlockResource;
use App\Filament\Resources\LearnerDevices\LearnerDeviceResource;
use App\Filament\Resources\Library\LibraryResource;
use App\Filament\Resources\ManualGrants\ManualGrantResource;
use App\Filament\Resources\RolePermissions\RolePermissionResource;
use App\Filament\Resources\StaffUsers\Pages\ListStaffUsers;
use App\Filament\Resources\StaffUsers\StaffUserResource;
use App\Filament\Resources\SubmissionReview\SubmissionReviewResource;
use App\Filament\Shared\WorkspaceAvatarProvider;
use App\Http\Middleware\EnforceTwoFactor;
use App\Http\Middleware\EnsureActiveAccount;
use Filament\Actions\Action;
use Filament\Enums\ThemeMode;
use Filament\Facades\Filament;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\Support\Enums\Width;
use Filament\Tables\View\TablesRenderHook;
use Filament\View\PanelsRenderHook;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

final class StaffPanelsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach (['admin', 'instructor', 'finance'] as $id) {
            Filament::registerPanel(static fn (): Panel => Panel::make()
                ->id($id)
                ->path($id)
                ->default($id === 'admin')
                ->login(WorkspaceLogin::class)
                ->sidebarWidth('var(--yg-sidebar-width)')
                ->defaultAvatarProvider(WorkspaceAvatarProvider::class)
                ->brandLogo(static fn () => view('filament.shared.brand'))
                ->brandLogoHeight('2.5rem')
                ->defaultThemeMode(ThemeMode::Light)
                ->font('Noto Sans Arabic', provider: LocalFontProvider::class)
                ->maxContentWidth(Width::Full)
                ->authGuard('web')
                ->brandName(static fn (): string => (string) __('app.name'))
                ->userMenuItems([Action::make('security')->label(static fn (): string => (string) __('pages.tf_title'))->url('/security/two-factor')->icon('heroicon-o-shield-check')])
                ->viteTheme('resources/css/filament-theme.css')
                ->renderHook(PanelsRenderHook::GLOBAL_SEARCH_AFTER, static fn () => view('filament.staff.locale-switcher', ['panel' => $id]))
                ->pages([WorkspaceDashboard::class])
                ->renderHook(TablesRenderHook::TOOLBAR_START, static fn () => view('filament.staff.users-toolbar', ['options' => Role::query()->where('guard_name', 'web')->get()->mapWithKeys(static fn (Role $role): array => [$role->id => __('roles.'.$role->name)])->all()]), scopes: [ListStaffUsers::class])
                ->renderHook(TablesRenderHook::CONTENT_BEFORE, static fn () => view('filament.shared.table-scroll-hint'))
                ->renderHook(TablesRenderHook::CONTENT_AFTER, static fn (array $data) => view('filament.shared.workspace-pagination', $data))
                ->resources(match ($id) {
                    'admin' => [
                        AuditLogResource::class, StaffUserResource::class, RolePermissionResource::class,
                        CategoryResource::class, CmsPageResource::class, HomepageBlockResource::class, CourseResource::class, ManualGrantResource::class, LibraryResource::class, LearnerDeviceResource::class, SubmissionReviewResource::class, EssayReviewResource::class, HelpRequestResource::class,
                    ],
                    'instructor' => [CourseResource::class, LibraryResource::class, SubmissionReviewResource::class, EssayReviewResource::class, HelpRequestResource::class],
                    default => [ManualGrantResource::class], // finance
                })
                ->middleware(['web', DisableBladeIconComponents::class, DispatchServingFilamentEvent::class])
                ->authMiddleware([Authenticate::class, EnsureActiveAccount::class, AuthenticateSession::class, EnforceTwoFactor::class], isPersistent: true));
        }
    }
}
