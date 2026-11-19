<?php

declare(strict_types=1);

namespace App\Filament\Shared;

use App\Filament\Pages\AssistantAvailability;
use App\Filament\Pages\AssistantControls;
use App\Filament\Pages\AssistantInsights;
use App\Filament\Pages\AssistantTestConsole;
use App\Filament\Pages\AssistantUsageReport;
use App\Filament\Pages\GeneralSettingsPage;
use App\Filament\Pages\InstructorAgreementPage;
use App\Filament\Pages\LearnerThreeSixtyPage;
use App\Filament\Pages\MarketingTags;
use App\Filament\Pages\MyPayoutProfile;
use App\Filament\Pages\PaymentPayeeSettings;
use App\Filament\Pages\PaymentReconciliationPage;
use App\Filament\Pages\ReportsPage;
use App\Filament\Pages\RetentionSettings;
use App\Filament\Pages\SafeguardingRecipientsSettings;
use App\Filament\Pages\SettingsHub;
use App\Filament\Pages\WorkspaceDashboard;
use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Filament\Resources\Audit\AuditLogResource;
use App\Filament\Resources\BlogCategories\BlogCategoryResource;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Filament\Resources\BlogTags\BlogTagResource;
use App\Filament\Resources\Bundles\BundleResource;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\CmsPages\CmsPageResource;
use App\Filament\Resources\CommissionRules\CommissionRuleResource;
use App\Filament\Resources\ContactEnquiries\ContactEnquiryResource;
use App\Filament\Resources\Coupons\CouponResource;
use App\Filament\Resources\CourseRevisions\CourseRevisionResource;
use App\Filament\Resources\Courses\CourseResource;
use App\Filament\Resources\DataRequests\DataRequestResource;
use App\Filament\Resources\EssayReview\EssayReviewResource;
use App\Filament\Resources\HelpRequests\HelpRequestResource;
use App\Filament\Resources\HomepageBlocks\HomepageBlockResource;
use App\Filament\Resources\InstructorAgreements\InstructorAgreementResource;
use App\Filament\Resources\InstructorApplications\InstructorApplicationResource;
use App\Filament\Resources\InstructorProfiles\InstructorProfileResource;
use App\Filament\Resources\LearnerDevices\LearnerDeviceResource;
use App\Filament\Resources\LedgerEntries\LedgerEntryResource;
use App\Filament\Resources\Library\LibraryResource;
use App\Filament\Resources\ManualGrants\ManualGrantResource;
use App\Filament\Resources\ManualPayments\ManualPaymentProofResource;
use App\Filament\Resources\MyEarnings\MyEarningsResource;
use App\Filament\Resources\MyStatements\MyStatementsResource;
use App\Filament\Resources\NewsletterSubscribers\NewsletterSubscriberResource;
use App\Filament\Resources\NotificationTemplates\NotificationTemplateResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\PayoutProfiles\PayoutProfileResource;
use App\Filament\Resources\Plans\PlanResource;
use App\Filament\Resources\Refunds\RefundResource;
use App\Filament\Resources\Reviews\CourseReviewResource;
use App\Filament\Resources\RolePermissions\RolePermissionResource;
use App\Filament\Resources\Settlements\SettlementResource;
use App\Filament\Resources\StaffUsers\StaffUserResource;
use App\Filament\Resources\Statements\StatementResource;
use App\Filament\Resources\SubmissionReview\SubmissionReviewResource;
use App\Filament\Resources\Subscriptions\SubscriptionResource;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;

/**
 * T-P9-09: one legacy-parity sidebar for the admin, finance and instructor panels (PRD 19.2, docs/LEGACY_PARITY.md).
 * Groups and order are listed once here; each panel shows only the classes it registers and only those the user can open
 * (canAccess), and a registered class missing from the catalog still shows under "More" so nothing is silently dropped.
 */
final class StaffNavigation
{
    /**
     * Items within a group run most-used first (queues before catalogues, catalogues before taxonomies).
     *
     * @var array<string, list<class-string>>
     */
    public const array GROUPS = [
        'courses' => [
            CourseResource::class, SubmissionReviewResource::class, EssayReviewResource::class, CourseReviewResource::class, CourseRevisionResource::class,
            LibraryResource::class, CategoryResource::class, LearnerDeviceResource::class,
        ],
        'site' => [
            BlogPostResource::class, CmsPageResource::class, HomepageBlockResource::class, InstructorProfileResource::class, BlogCategoryResource::class, BlogTagResource::class,
        ],
        'sales' => [
            OrderResource::class, ManualPaymentProofResource::class, RefundResource::class, PaymentReconciliationPage::class, SubscriptionResource::class,
            PlanResource::class, BundleResource::class, CouponResource::class, ManualGrantResource::class,
        ],
        'finance' => [
            SettlementResource::class, StatementResource::class, LedgerEntryResource::class, CommissionRuleResource::class, PayoutProfileResource::class,
            MyEarningsResource::class, MyStatementsResource::class, MyPayoutProfile::class, InstructorAgreementPage::class,
        ],
        'people' => [
            LearnerThreeSixtyPage::class, InstructorApplicationResource::class, StaffUserResource::class, InstructorAgreementResource::class, RolePermissionResource::class,
        ],
        'inbox' => [
            HelpRequestResource::class, ContactEnquiryResource::class, AnnouncementResource::class, NewsletterSubscriberResource::class,
        ],
        'reports' => [ReportsPage::class],
        'ai' => [AssistantTestConsole::class, AssistantInsights::class, AssistantAvailability::class, AssistantUsageReport::class, AssistantControls::class],
        'settings' => [
            SettingsHub::class, GeneralSettingsPage::class, PaymentPayeeSettings::class, NotificationTemplateResource::class, DataRequestResource::class,
            MarketingTags::class, RetentionSettings::class, SafeguardingRecipientsSettings::class, AuditLogResource::class,
        ],
    ];

    /**
     * Daily-use order of the groups per panel (Dashboard is always first, ungrouped). A group missing here follows in catalog order.
     *
     * @var array<string, list<string>>
     */
    public const array PANEL_ORDER = [
        'admin' => ['inbox', 'sales', 'courses', 'finance', 'reports', 'people', 'site', 'ai', 'settings'],
        'finance' => ['sales', 'finance', 'reports', 'inbox', 'people', 'settings'],
        'instructor' => ['courses', 'inbox', 'finance', 'reports', 'ai', 'sales'],
    ];

    /** The work-queue group each panel keeps open next to the active one. */
    public const array QUEUE_GROUP = ['admin' => 'inbox', 'finance' => 'sales', 'instructor' => 'inbox'];

    /** @return list<string> group keys in sidebar order for a panel */
    public static function order(string $panelId): array
    {
        $first = self::PANEL_ORDER[$panelId] ?? [];

        return [...$first, ...array_values(array_diff(array_keys(self::GROUPS), $first))];
    }

    public static function build(NavigationBuilder $builder): NavigationBuilder
    {
        $panel = Filament::getCurrentOrDefaultPanel();
        $registered = [...$panel->getPages(), ...$panel->getResources()];
        $placed = [WorkspaceDashboard::class];

        $builder->items(self::items([WorkspaceDashboard::class], $registered));

        $groups = [];
        $queue = self::QUEUE_GROUP[$panel->getId()] ?? 'inbox';
        foreach (self::order($panel->getId()) as $key) {
            $classes = self::GROUPS[$key];
            $placed = [...$placed, ...$classes];
            $items = self::items($classes, $registered);
            if ($items !== []) {
                $group = NavigationGroup::make(self::label($key))->items($items);
                $active = $group->isActive();
                $group->collapsed($key !== $queue && ! $active)->collapsible();

                if ($active) {
                    $group->extraSidebarAttributes([
                        'x-init' => '$store.sidebar.groupIsCollapsed(label) && $store.sidebar.toggleCollapsedGroup(label)',
                    ]);
                }

                $groups[] = $group;
            }
        }

        $others = self::items(array_values(array_filter($registered, static fn (string $class): bool => ! in_array($class, $placed, true))), $registered);
        if ($others !== []) {
            $groups[] = NavigationGroup::make(self::label('more'))->items($others);
        }

        return $builder->groups($groups);
    }

    /** Which group a class belongs to, or null when the catalog does not list it (it then shows under "More"). */
    public static function groupOf(string $class): ?string
    {
        foreach (self::GROUPS as $key => $classes) {
            if (in_array($class, $classes, true)) {
                return $key;
            }
        }

        return null;
    }

    private static function label(string $group): string
    {
        return (string) __('navigation.groups.'.$group);
    }

    /**
     * @param  list<class-string>  $classes
     * @param  array<array-key, class-string>  $registered
     * @return list<NavigationItem>
     */
    private static function items(array $classes, array $registered): array
    {
        $items = [];
        $sort = 0;

        foreach ($classes as $class) {
            if (! in_array($class, $registered, true) || ! $class::shouldRegisterNavigation() || ! $class::canAccess()) {
                continue;
            }

            foreach ($class::getNavigationItems() as $item) {
                $items[] = $item->group(null)->sort(++$sort);
            }
        }

        return $items;
    }
}
