<?php

declare(strict_types=1);

namespace App\Domain\AI;

/** Why the assistant is not offered. The value is the lang key under ai.unavailable.*. */
enum UnavailableReason: string
{
    case GlobalOff = 'global_off';
    case CourseOff = 'course_off';
    case ItemOff = 'item_off';
    case NotRecordedLesson = 'not_recorded_lesson';
    case NoEntitlement = 'no_entitlement';
    case NoApprovedDocument = 'no_approved_document';
    case QuotaExhausted = 'quota_exhausted';
    case ConsentRequired = 'consent_required';
    case MinorsOff = 'minors_off';
}
