<?php

declare(strict_types=1);

namespace App\Domain\Certificates\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Certificates\Certificate;
use App\Domain\Completion\CourseCompletion;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Issues the certificate of a completion. Idempotent: a completion that already has a certificate (live or revoked) gets
 * nothing new, so repeated events never duplicate it and a staff-revoked certificate is never silently re-issued.
 * Staff re-issue goes through ReissueCertificate, which calls create().
 */
final class IssueCertificate
{
    private const string ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(private readonly AuditLogger $audit) {}

    /** @return Certificate|null the new certificate, or null when the completion already has one */
    public function __invoke(CourseCompletion $completion): ?Certificate
    {
        if (Certificate::query()->where('course_completion_id', $completion->getKey())->exists()) {
            return null;
        }

        try {
            return $this->create($completion, 'certificate.issued', null, []);
        } catch (UniqueConstraintViolationException) {
            return null; // a parallel request issued it first
        }
    }

    /**
     * Writes one live certificate and its audit row in one transaction.
     *
     * @param  array<string, mixed>  $context
     */
    public function create(CourseCompletion $completion, string $event, ?int $actorId, array $context): Certificate
    {
        $completion->loadMissing(['learner', 'course']);
        $learner = $completion->learner;
        $course = $completion->course;

        return DB::transaction(function () use ($completion, $learner, $course, $event, $actorId, $context): Certificate {
            $name = filled($learner->getAttribute('display_name')) ? $learner->getAttribute('display_name') : $learner->getAttribute('name');
            $certificate = Certificate::query()->create([
                'course_completion_id' => $completion->getKey(),
                'course_id' => $completion->course_id,
                'learner_id' => $completion->learner_id,
                'number' => $this->number(),
                'token' => Str::random(40),
                'active' => 1,
                'issued_at' => now(),
                'learner_name' => mb_substr((string) $name, 0, 191),
                'course_title_ar' => mb_substr((string) $course->getTranslation('title', 'ar', false), 0, 255) ?: null,
                'course_title_en' => mb_substr((string) $course->getTranslation('title', 'en', false), 0, 255) ?: null,
                'completed_on' => $completion->completed_at->toDateString(),
            ]);

            $after = ['number' => $certificate->number, 'learner_id' => $certificate->learner_id, 'completion_id' => $completion->getKey()] + $context;
            if ($this->audit->record($event, $certificate, $actorId, null, $after) === null) {
                throw new RuntimeException('Unable to record certificate audit.');
            }

            return $certificate;
        });
    }

    private function number(): string
    {
        do {
            $suffix = '';
            for ($i = 0; $i < 8; $i++) {
                $suffix .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            $number = 'YG-'.now()->format('Y').'-'.$suffix;
        } while (Certificate::query()->where('number', $number)->exists());

        return $number;
    }
}
