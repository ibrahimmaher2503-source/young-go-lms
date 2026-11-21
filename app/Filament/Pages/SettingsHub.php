<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Resources\Audit\AuditLogResource;
use App\Filament\Resources\DataRequests\DataRequestResource;
use App\Filament\Resources\NotificationTemplates\NotificationTemplateResource;
use App\Filament\Resources\RolePermissions\RolePermissionResource;
use App\Models\User;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Support\Facades\Auth;

/** T-P9-09: one hub that links every settings page the viewer may open. The hub changes nothing; each target authorizes and audits its own writes. */
final class SettingsHub extends Page
{
    protected static ?string $slug = 'settings';

    protected string $view = 'filament.settings.hub';

    /** @var array<string, list<string>> */
    public const array GROUPS = [
        'platform' => ['general', 'payee'],
        'compliance' => ['notifications', 'marketing', 'privacy', 'safeguarding', 'data_requests'],
        'governance' => ['ai', 'roles', 'audit'],
    ];

    /** @var array<string, class-string> */
    public const array ENTRIES = [
        'general' => GeneralSettingsPage::class,
        'payee' => PaymentPayeeSettings::class,
        'notifications' => NotificationTemplateResource::class,
        'marketing' => MarketingTags::class,
        'privacy' => RetentionSettings::class,
        'safeguarding' => SafeguardingRecipientsSettings::class,
        'data_requests' => DataRequestResource::class,
        'ai' => AssistantControls::class,
        'roles' => RolePermissionResource::class,
        'audit' => AuditLogResource::class,
    ];

    public static function getNavigationLabel(): string
    {
        return __('settings.hub.nav');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-cog-8-tooth';
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->getAttribute('status') === 'active' && ($user->can('system.settings.manage') || self::entries() !== []);
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);
    }

    #[Unexposed]
    public function getTitle(): string
    {
        return __('settings.hub.title');
    }

    /** @return array<string, string> */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    /** @return list<array{key: string, label: string, description: string, url: string, icon: string}> */
    public static function entries(): array
    {
        $entries = [];

        foreach (self::ENTRIES as $key => $class) {
            $allowed = is_subclass_of($class, Resource::class) ? $class::canViewAny() : $class::canAccess();
            if (! $allowed) {
                continue;
            }

            $icon = $class::getNavigationIcon();
            $entries[] = [
                'key' => $key,
                'label' => (string) $class::getNavigationLabel(),
                'description' => (string) __('settings.hub.entries.'.$key),
                'url' => $class::getUrl(),
                'icon' => $icon,
            ];
        }

        return $entries;
    }

    /** @return list<array{key: string, label: string, items: list<array{key: string, label: string, description: string, url: string, icon: string}>}> */
    public static function groupedEntries(): array
    {
        $byKey = [];
        foreach (self::entries() as $entry) {
            $byKey[$entry['key']] = $entry;
        }

        $groups = [];
        foreach (self::GROUPS as $groupKey => $keys) {
            $items = [];
            foreach ($keys as $key) {
                if (isset($byKey[$key])) {
                    $items[] = $byKey[$key];
                }
            }
            if ($items !== []) {
                $groups[] = [
                    'key' => $groupKey,
                    'label' => (string) __('settings.hub.groups.'.$groupKey),
                    'items' => $items,
                ];
            }
        }

        return $groups;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return ['entries' => self::entries(), 'groups' => self::groupedEntries()];
    }
}
