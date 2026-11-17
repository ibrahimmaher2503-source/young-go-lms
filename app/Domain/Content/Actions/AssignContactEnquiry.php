<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Audit\AuditLogger;
use App\Models\ContactEnquiry;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Assigns an enquiry to an active staff member who may handle leads, or clears the assignee (null). */
final class AssignContactEnquiry
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $actor, ContactEnquiry $enquiry, ?User $assignee): void
    {
        Gate::forUser($actor)->authorize('marketing.lead.view');

        if ($assignee !== null && ($assignee->status !== 'active' || ! $assignee->can('marketing.lead.view'))) {
            throw ValidationException::withMessages(['assignee' => __('contact.errors.assignee')]);
        }

        $before = $enquiry->assigned_to_id;
        $enquiry->update(['assigned_to_id' => $assignee?->getKey()]);

        if ($before !== $enquiry->assigned_to_id) {
            $this->audit->record('contact.enquiry_assigned', $enquiry, (int) $actor->getKey(), ['assigned_to_id' => $before], ['assigned_to_id' => $enquiry->assigned_to_id]);
        }
    }
}
