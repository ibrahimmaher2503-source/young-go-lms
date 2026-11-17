<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Content\ContactEnquiryStatus;
use App\Domain\Shared\Phone\PhoneNumbers;
use App\Models\ContactEnquiry;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Stores a validated public enquiry as "new" and records an anonymous audit entry. The audit entry carries no personal
 * data (YG-WEB-007); spam filtering (honeypot, rate limit) happens before this action is reached.
 */
final class SubmitContactEnquiry
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param  array{name: string, email: string, phone?: string|null, message: string}  $input */
    public function __invoke(array $input, string $locale): ContactEnquiry
    {
        $phone = isset($input['phone']) && trim($input['phone']) !== '' ? $this->phone($input['phone']) : null;

        $enquiry = ContactEnquiry::query()->create([
            'name' => trim($input['name']),
            'email' => mb_strtolower(trim($input['email'])),
            'phone' => $phone,
            'message' => trim($input['message']),
            'locale' => $locale,
            'status' => ContactEnquiryStatus::New,
        ]);

        $this->audit->record('contact.enquiry_received', $enquiry, null, null, null, ['locale' => $locale]);

        return $enquiry;
    }

    private function phone(string $number): string
    {
        try {
            return PhoneNumbers::normalize($number);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['phone' => __('contact.errors.phone')]);
        }
    }
}
