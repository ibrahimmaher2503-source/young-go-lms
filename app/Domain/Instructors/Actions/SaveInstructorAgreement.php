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
 * Creates or edits a DRAFT agreement version (needs `instructor.manage`). Title and body are required in Arabic and English.
 * A published version is immutable: wording changes need a new version, which then requires re-acceptance.
 */
final class SaveInstructorAgreement
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param  array<string, mixed>  $data  version (new only), title and body as locale => text */
    public function __invoke(User $actor, ?InstructorAgreement $agreement, array $data): InstructorAgreement
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('instructor.manage')) {
            throw new AuthorizationException;
        }
        if ($agreement !== null && $agreement->isPublished()) {
            throw ValidationException::withMessages(['version' => __('instructor_onboarding.agreement.published_locked')]);
        }

        $title = $this->texts((array) ($data['title'] ?? []), 'title', 160);
        $body = $this->texts((array) ($data['body'] ?? []), 'body', 20000);
        $version = trim((string) ($agreement !== null ? $agreement->version : ($data['version'] ?? '')));
        if (preg_match('/^[a-z0-9][a-z0-9._-]{0,62}$/', $version) !== 1) {
            throw ValidationException::withMessages(['version' => __('instructor_onboarding.agreement.version_invalid')]);
        }
        if ($agreement === null && InstructorAgreement::query()->where('version', $version)->exists()) {
            throw ValidationException::withMessages(['version' => __('instructor_onboarding.agreement.version_taken')]);
        }

        return DB::transaction(function () use ($actor, $agreement, $version, $title, $body): InstructorAgreement {
            $record = $agreement ?? new InstructorAgreement(['version' => $version, 'status' => InstructorAgreement::DRAFT]);
            $record->setTranslations('title', $title);
            $record->setTranslations('body', $body);
            $record->save();

            if ($this->audit->record('instructor_agreement.saved', $record, (int) $actor->getKey(), null, ['version' => $record->version, 'status' => $record->status]) === null) {
                throw new RuntimeException('Unable to record agreement audit.');
            }

            return $record;
        });
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<string, string>
     */
    private function texts(array $values, string $field, int $max): array
    {
        $clean = [];
        foreach (['ar', 'en'] as $locale) {
            $text = trim((string) ($values[$locale] ?? ''));
            if ($text === '' || mb_strlen($text) > $max) {
                throw ValidationException::withMessages([$field => __('instructor_onboarding.agreement.text_invalid')]);
            }
            $clean[$locale] = $text;
        }

        return $clean;
    }
}
