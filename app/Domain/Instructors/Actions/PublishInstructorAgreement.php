<?php

declare(strict_types=1);

namespace App\Domain\Instructors\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Instructors\Models\InstructorAgreement;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Publishes a draft agreement version (needs `instructor.manage`). From that moment every independent instructor who has not
 * accepted it is blocked from creating new courses until they accept (A2). Publishing a published version is a no-op.
 */
final class PublishInstructorAgreement
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $actor, InstructorAgreement $agreement): InstructorAgreement
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('instructor.manage')) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($actor, $agreement): InstructorAgreement {
            $locked = InstructorAgreement::query()->lockForUpdate()->findOrFail($agreement->getKey());
            if ($locked->isPublished()) {
                return $locked;
            }
            foreach (['title', 'body'] as $field) {
                foreach (['ar', 'en'] as $locale) {
                    if (trim((string) $locked->getTranslation($field, $locale, false)) === '') {
                        throw ValidationException::withMessages(['version' => __('instructor_onboarding.agreement.text_invalid')]);
                    }
                }
            }

            $locked->forceFill(['status' => InstructorAgreement::PUBLISHED, 'published_at' => now(), 'published_by' => $actor->getKey()])->save();
            if ($this->audit->record('instructor_agreement.published', $locked, (int) $actor->getKey(), ['status' => InstructorAgreement::DRAFT], ['version' => $locked->version, 'status' => InstructorAgreement::PUBLISHED]) === null) {
                throw new RuntimeException('Unable to record agreement audit.');
            }

            return $locked;
        });
    }
}
