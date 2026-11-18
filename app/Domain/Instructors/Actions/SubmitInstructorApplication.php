<?php

declare(strict_types=1);

namespace App\Domain\Instructors\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\ActingLearner;
use App\Domain\Instructors\ApplicationDocumentFiles;
use App\Domain\Instructors\ApplicationStatus;
use App\Domain\Instructors\Models\InstructorApplication;
use App\Domain\Instructors\Queries\PublicInstructors;
use App\Domain\Shared\Phone\PhoneNumbers;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\File;
use Throwable;

/**
 * An active adult account applies to teach as an independent instructor (YG-GAP-INS-001). Child accounts, parents acting
 * for a child, unverified emails and existing instructors are refused; one pending application per account (database
 * unique). The ID document is content-validated and stored privately. Spam filtering (honeypot, rate limit) happens before
 * this action. The audit entry carries ids only: no name, phone, links or document details.
 */
final class SubmitInstructorApplication
{
    public function __construct(private readonly AuditLogger $audit, private readonly ActingLearner $acting) {}

    /**
     * @param  array<string, mixed>  $data  full_name, phone?, discipline, experience_years?, bio, portfolio_links (list of urls)
     */
    public function __invoke(User $applicant, array $data, File $idDocument, string $locale): InstructorApplication
    {
        $this->assertEligible($applicant);
        $values = $this->clean($data);
        $stored = null;

        try {
            return DB::transaction(function () use ($applicant, $values, $idDocument, $locale, &$stored): InstructorApplication {
                $locked = User::query()->lockForUpdate()->findOrFail($applicant->getKey());
                if (InstructorApplication::query()->where('user_id', $locked->getKey())->where('status', ApplicationStatus::Pending->value)->exists()) {
                    $this->refuse('application', 'already_pending');
                }

                $stored = ApplicationDocumentFiles::store($idDocument);
                $application = InstructorApplication::query()->create($values + [
                    'user_id' => $locked->getKey(), 'pending_user_id' => $locked->getKey(), 'locale' => $locale === 'ar' ? 'ar' : 'en',
                    'id_document_path' => $stored['path'], 'id_document_mime' => $stored['mime'],
                    'id_document_size' => $stored['size'], 'id_document_sha256' => $stored['sha256'],
                    'status' => ApplicationStatus::Pending->value,
                ]);

                if ($this->audit->record('instructor_application.submitted', $application, (int) $locked->getKey(), null, ['status' => ApplicationStatus::Pending->value]) === null) {
                    throw new RuntimeException('Unable to record instructor application audit.');
                }

                return $application;
            });
        } catch (UniqueConstraintViolationException) {
            $this->discard($stored);
            $this->refuse('application', 'already_pending'); // a concurrent submit won the race
        } catch (Throwable $failure) {
            $this->discard($stored);
            throw $failure;
        }
    }

    private function assertEligible(User $applicant): void
    {
        if ($applicant->getAttribute('status') !== 'active' || $applicant->getAttribute('email') === null || $this->acting->isParentContext()) {
            $this->refuse('application', 'not_eligible');
        }
        if (! $applicant->hasVerifiedEmail()) {
            $this->refuse('application', 'verify_email');
        }
        if ($applicant->hasAnyRole(PublicInstructors::ROLES)) {
            $this->refuse('application', 'already_instructor');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{full_name: string, phone: string|null, discipline: string, experience_years: int|null, bio: string, portfolio_links: list<string>}
     */
    private function clean(array $data): array
    {
        $name = trim((string) ($data['full_name'] ?? ''));
        $discipline = trim((string) ($data['discipline'] ?? ''));
        $bio = trim((string) ($data['bio'] ?? ''));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            $this->refuse('full_name', 'name_invalid');
        }
        if (mb_strlen($discipline) < 2 || mb_strlen($discipline) > 120) {
            $this->refuse('discipline', 'discipline_invalid');
        }
        if (mb_strlen($bio) < 30 || mb_strlen($bio) > 2000) {
            $this->refuse('bio', 'bio_invalid');
        }

        $years = $data['experience_years'] ?? null;
        $years = $years === null || $years === '' ? null : (int) $years;
        if ($years !== null && ($years < 0 || $years > 70)) {
            $this->refuse('experience_years', 'years_invalid');
        }

        $phone = isset($data['phone']) && trim((string) $data['phone']) !== '' ? $this->phone((string) $data['phone']) : null;

        $links = [];
        foreach ((array) ($data['portfolio_links'] ?? []) as $link) {
            $link = trim((string) $link);
            if ($link === '') {
                continue;
            }
            if (mb_strlen($link) > 255 || preg_match('#^https?://[^\s<>"\']+$#i', $link) !== 1 || filter_var($link, FILTER_VALIDATE_URL) === false) {
                $this->refuse('portfolio_links', 'link_invalid');
            }
            $links[] = $link;
        }
        $links = array_values(array_unique($links));
        $max = Config::integer('instructor_onboarding.max_portfolio_links');
        if ($links === [] || count($links) > $max) {
            $this->refuse('portfolio_links', 'links_count', ['max' => $max]);
        }

        return ['full_name' => $name, 'phone' => $phone, 'discipline' => $discipline, 'experience_years' => $years, 'bio' => $bio, 'portfolio_links' => $links];
    }

    private function phone(string $number): string
    {
        try {
            return PhoneNumbers::normalize($number);
        } catch (InvalidArgumentException) {
            $this->refuse('phone', 'phone_invalid');
        }
    }

    /** @param  array{path: string}|null  $stored */
    private function discard(?array $stored): void
    {
        if ($stored !== null) {
            ApplicationDocumentFiles::delete($stored['path']);
        }
    }

    /** @param  array<string, mixed>  $replace */
    private function refuse(string $field, string $key, array $replace = []): never
    {
        throw ValidationException::withMessages([$field => __('instructor_onboarding.errors.'.$key, $replace)]);
    }
}
