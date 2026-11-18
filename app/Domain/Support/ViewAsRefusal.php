<?php

declare(strict_types=1);

namespace App\Domain\Support;

/** Why a "view as learner" session may not start (ED-027). The value is the language key under `support.refusal`. */
enum ViewAsRefusal: string
{
    case Permission = 'permission';
    case OwnAccount = 'own_account';
    case StaffAccount = 'staff_account';
    case NotLearner = 'not_learner';
    case Inactive = 'inactive';
    case Anonymized = 'anonymized';
    case PrivacyRequest = 'privacy_request';
    case Safeguarding = 'safeguarding';
    case MinorRestricted = 'minor_restricted';
    case AlreadyActive = 'already_active';
    case ParentContext = 'parent_context';

    public function label(): string
    {
        return __('support.refusal.'.$this->value);
    }
}
