<?php

declare(strict_types=1);

namespace App\Filament\Resources\ContactEnquiries\Pages;

use App\Domain\Content\Actions\RecordLeadExport;
use App\Domain\Content\ContactEnquiryStatus;
use App\Filament\Resources\ContactEnquiries\ContactEnquiryResource;
use App\Filament\Shared\Actions\ExportCsvAction;
use App\Filament\Shared\Pages\WorkspaceListRecords;
use App\Models\ContactEnquiry;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

final class ListContactEnquiries extends WorkspaceListRecords
{
    protected static string $resource = ContactEnquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [ExportCsvAction::make('marketing.lead.view', [
            __('contact.fields.name') => 'name',
            __('contact.fields.email') => 'email',
            __('contact.fields.phone') => 'phone',
            __('contact.fields.message') => 'message',
            __('contact.admin.status') => 'status',
            __('contact.admin.assignee') => 'assignee.name',
            __('contact.admin.received') => 'created_at',
        ], 'contact-enquiries.csv', static function (): void {
            $user = Auth::user();
            if ($user instanceof User) {
                app(RecordLeadExport::class)($user, 'contact_enquiries');
            }
        })->icon('heroicon-o-arrow-down-tray')];
    }

    /** @return array<array{label: string, value: int|string, icon?: string, tone?: string}> */
    protected function getWorkspaceSummary(): array
    {
        return [
            ['label' => __('contact.admin.summary.new'), 'value' => ContactEnquiry::query()->where('status', ContactEnquiryStatus::New)->count(), 'icon' => 'heroicon-o-envelope', 'tone' => 'warning'],
            ['label' => __('contact.admin.summary.in_progress'), 'value' => ContactEnquiry::query()->where('status', ContactEnquiryStatus::InProgress)->count(), 'icon' => 'heroicon-o-clock'],
            ['label' => __('contact.admin.summary.unassigned'), 'value' => ContactEnquiry::query()->whereNull('assigned_to_id')->whereIn('status', [ContactEnquiryStatus::New, ContactEnquiryStatus::InProgress])->count(), 'icon' => 'heroicon-o-user-minus'],
        ];
    }
}
