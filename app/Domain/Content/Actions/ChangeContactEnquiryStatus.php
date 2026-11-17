<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Content\ContactEnquiryStatus;
use App\Models\ContactEnquiry;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/** Moves an enquiry to another inbox state; resolving or marking spam stamps the handled time. */
final class ChangeContactEnquiryStatus
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $actor, ContactEnquiry $enquiry, ContactEnquiryStatus $to): void
    {
        Gate::forUser($actor)->authorize('marketing.lead.view');

        $from = $enquiry->status;
        if ($from === $to) {
            return;
        }

        $closing = in_array($to, [ContactEnquiryStatus::Resolved, ContactEnquiryStatus::Spam], true);
        $enquiry->update(['status' => $to, 'handled_at' => $closing ? now() : null]);

        $this->audit->record('contact.enquiry_status_changed', $enquiry, (int) $actor->getKey(), ['status' => $from->value], ['status' => $to->value]);
    }
}
