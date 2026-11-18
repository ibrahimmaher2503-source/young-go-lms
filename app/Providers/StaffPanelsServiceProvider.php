<?php

declare(strict_types=1);

namespace App\Providers;

use App\Filament\Auth\WorkspaceLogin;
use App\Filament\Pages\AssistantAvailability;
use App\Filament\Pages\AssistantControls;
use App\Filament\Pages\AssistantInsights;
use App\Filament\Pages\AssistantTestConsole;
use App\Filament\Pages\AssistantUsageReport;
use App\Filament\Pages\InstructorAgreementPage;
use App\Filament\Pages\MarketingTags;
use App\Filament\Pages\MyPayoutProfile;
use App\Filament\Pages\PaymentReconciliationPage;
use App\Filament\Pages\WorkspaceDashboard;
use App\Filament\Resources\Audit\AuditLogResource;
use App\Filament\Resources\BlogCategories\BlogCategoryResource;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Filament\Resources\BlogTags\BlogTagResource;
use App\Filament\Resources\Bundles\BundleResource;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\CmsPages\CmsPageResource;
use App\Filament\Resources\ContactEnquiries\ContactEnquiryResource;
use App\Filament\Resources\Coupons\CouponResource;
use App\Filament\Resources\Courses\CourseResource;
use App\Filament\Resources\EssayReview\EssayReviewResource;
use App\Filament\Resources\HelpRequests\HelpRequestResource;
use App\Filament\Resources\HomepageBlocks\HomepageBlockResource;
use App\Filament\Resources\InstructorAgreements\InstructorAgreementResource;
use App\Filament\Resources\InstructorApplications\InstructorApplicationResource;
use App\Filament\Resources\InstructorProfiles\InstructorProfileResource;
use App\Filament\Resources\LearnerDevices\LearnerDeviceResource;
use App\Filament\Resources\Library\LibraryResource;
use App\Filament\Resources\ManualGrants\ManualGrantResource;
use App\Filament\Resources\ManualPayments\ManualPaymentProofResource;
use App\Filament\Resources\NewsletterSubscribers\NewsletterSubscriberResource;
use App\Filament\Resources\PayoutProfiles\PayoutProfileResource;
use App\Filament\Resources\Reviews\CourseReviewResource;
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
                ->pages(match ($id) {
                    'finance' => [WorkspaceDashboard::class, PaymentReconciliationPage::class],
                    'admin' => [WorkspaceDashboard::class, PaymentReconciliationPage::class, MarketingTags::class, AssistantTestConsole::class, AssistantControls::class, AssistantUsageReport::class, AssistantInsights::class, AssistantAvailability::class],
                    default => [WorkspaceDashboard::class, AssistantTestConsole::class, AssistantInsights::class, AssistantAvailability::class, InstructorAgreementPage::class, MyPayoutProfile::class],
                })
                ->renderHook(TablesRenderHook::TOOLBAR_START, static fn () => view('filament.staff.users-toolbar', ['options' => Role::query()->where('guard_name', 'web')->get()->mapWithKeys(static fn (Role $role): array => [$role->id => __('roles.'.$role->name)])->all()]), scopes: [ListStaffUsers::class])
                ->renderHook(TablesRenderHook::CONTENT_BEFORE, static fn () => view('filament.shared.table-scroll-hint'))
                ->renderHook(TablesRenderHook::CONTENT_AFTER, static fn (array $data) => view('filament.shared.workspace-pagination', $data))
                ->resources(match ($id) {
                    'admin' => [
                        AuditLogResource::class, StaffUserResource::class, RolePermissionResource::class,
                        ContactEnquiryResource::class, NewsletterSubscriberResource::class,
                        CategoryResource::class, CmsPageResource::class, BlogPostResource::class, BlogCategoryResource::class, BlogTagResource::class, InstructorProfileResource::class, HomepageBlockResource::class, CourseResource::class, ManualGrantResource::class, LibraryResource::class, LearnerDeviceResource::class, SubmissionReviewResource::class, EssayReviewResource::class, HelpRequestResource::class, CouponResource::class, BundleResource::class, ManualPaymentProofResource::class, \App\Filament\Resources\Refunds\RefundResource::class,
                        CourseReviewResource::class,
                        InstructorApplicationResource::class, InstructorAgreementResource::class,
                        \App\Filament\Resources\NotificationTemplates\NotificationTemplateResource::class, \App\Filament\Resources\Announcements\AnnouncementResource::class,
                    ],
                    'instructor' => [CourseResource::class, LibraryResource::class, SubmissionReviewResource::class, EssayReviewResource::class, HelpRequestResource::class, \App\Filament\Resources\Announcements\AnnouncementResource::class],
                    default => [ManualGrantResource::class, CouponResource::class, BundleResource::class, ManualPaymentProofResource::class, \App\Filament\Resources\Refunds\RefundResource::class, PayoutProfileResource::class], // finance
                })
                ->middleware(['web', DisableBladeIconComponents::class, DispatchServingFilamentEvent::class])
                ->authMiddleware([Authenticate::class, EnsureActiveAccount::class, AuthenticateSession::class, EnforceTwoFactor::class], isPersistent: true));
        }
    }
}
