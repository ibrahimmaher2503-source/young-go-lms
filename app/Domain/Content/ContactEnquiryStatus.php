<?php

declare(strict_types=1);

namespace App\Domain\Content;

/** Inbox states of a contact enquiry. */
enum ContactEnquiryStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Spam = 'spam';

    public function label(): string
    {
        return __('contact.statuses.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::InProgress => 'info',
            self::Resolved => 'success',
            self::Spam => 'gray',
        };
    }
}
