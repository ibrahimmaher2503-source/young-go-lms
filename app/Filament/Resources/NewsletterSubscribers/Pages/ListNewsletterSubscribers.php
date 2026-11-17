<?php

declare(strict_types=1);

namespace App\Filament\Resources\NewsletterSubscribers\Pages;

use App\Domain\Content\Actions\RecordLeadExport;
use App\Filament\Resources\NewsletterSubscribers\NewsletterSubscriberResource;
use App\Filament\Shared\Actions\ExportCsvAction;
use App\Filament\Shared\Pages\WorkspaceListRecords;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

final class ListNewsletterSubscribers extends WorkspaceListRecords
{
    protected static string $resource = NewsletterSubscriberResource::class;

    protected function getHeaderActions(): array
    {
        return [ExportCsvAction::make('marketing.lead.view', [
            __('contact.fields.email') => 'email',
            __('contact.admin.language') => 'locale',
            __('contact.admin.source') => 'source',
            __('contact.admin.consent_version') => 'consent_version',
            __('contact.admin.consented') => 'consented_at',
            __('contact.admin.unsubscribed') => 'unsubscribed_at',
        ], 'newsletter-subscribers.csv', static function (): void {
            $user = Auth::user();
            if ($user instanceof User) {
                app(RecordLeadExport::class)($user, 'newsletter_subscribers');
            }
        })->icon('heroicon-o-arrow-down-tray')];
    }

    /** @return array<array{label: string, value: int|string, icon?: string, tone?: string}> */
    protected function getWorkspaceSummary(): array
    {
        return [
            ['label' => __('contact.admin.summary.subscribed'), 'value' => NewsletterSubscriber::query()->whereNull('unsubscribed_at')->count(), 'icon' => 'heroicon-o-megaphone', 'tone' => 'info'],
            ['label' => __('contact.admin.summary.unsubscribed'), 'value' => NewsletterSubscriber::query()->whereNotNull('unsubscribed_at')->count(), 'icon' => 'heroicon-o-x-circle'],
        ];
    }
}
