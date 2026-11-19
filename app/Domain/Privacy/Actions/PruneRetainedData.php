<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Actions;

use App\Domain\AI\Admin\Actions\PruneAiData;
use App\Domain\Audit\AuditLogger;
use App\Domain\Privacy\ProtectedData;
use App\Domain\Privacy\RetentionClass;
use App\Domain\Privacy\RetentionPolicies;
use App\Domain\Privacy\RetentionPolicy;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Scheduled retention pruning (YG-GAP-PRV-002, O-025). Configurable classes without a value keep their data. Learning
 * records use the account closure date. Help requests run before AI messages because help requests pin AI messages.
 */
final class PruneRetainedData
{
    private const int LEARNING_RETENTION_YEARS = 2;

    /** Includes tables reached by cascades from quiz attempts and assignment submissions. */
    private const array LEARNING_TABLES = [
        'lesson_progress', 'course_progress', 'live_attendance', 'course_completions',
        'quiz_attempts', 'quiz_answers', 'assignment_submissions', 'submission_revisions',
        'submission_files', 'submission_decisions', 'submission_feedback',
    ];

    public function __construct(
        private readonly RetentionPolicies $policies,
        private readonly PruneAiData $pruneAi,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  list<RetentionClass>|null  $only  limit to some classes (null = all)
     * @return array<string, int> class value => rows or files removed
     */
    public function __invoke(?array $only = null): array
    {
        $report = [];
        foreach ($this->order() as $class) {
            if ($only !== null && ! in_array($class, $only, true)) {
                continue;
            }
            ProtectedData::assertNotProtected($class->tables());

            $days = $this->policies->days($class);
            $count = match ($class) {
                RetentionClass::DataExports => $this->exports(),
                RetentionClass::AiMessages => $days === null ? 0 : array_sum(($this->pruneAi)()),
                default => $days === null ? 0 : $this->prune($class, now()->subDays(max($days, Config::integer('privacy.min_retention_days')))),
            };
            $report[$class->value] = $count;
            $this->remember($class->value, $count, ['retention_days' => $days]);
        }

        if ($only === null) {
            ProtectedData::assertNotProtected(self::LEARNING_TABLES);
            $count = $this->policies->days(RetentionClass::SubmissionFiles) === null ? $this->submissionFiles(null) : 0;
            $count += $this->pruneLearningRecords();
            $report['learning_records'] = $count;
            $this->remember('learning_records', $count, ['years_after_closure' => self::LEARNING_RETENTION_YEARS]);
        }

        return $report;
    }

    /** @return list<RetentionClass> */
    private function order(): array
    {
        return [
            RetentionClass::DataExports, RetentionClass::SessionLogs, RetentionClass::OperationalLogs, RetentionClass::ContactEnquiries,
            RetentionClass::NewsletterUnsubscribed, RetentionClass::SubmissionFiles, RetentionClass::HelpRequests, RetentionClass::AiMessages,
        ];
    }

    private function prune(RetentionClass $class, Carbon $cutoff): int
    {
        return match ($class) {
            RetentionClass::SessionLogs => DB::table('user_sessions')->whereNotNull('signed_out_at')->where('signed_out_at', '<', $cutoff)->delete(),
            RetentionClass::OperationalLogs => $this->operationalLogs($cutoff),
            RetentionClass::ContactEnquiries => DB::table('contact_enquiries')->where('status', '!=', 'new')->where('created_at', '<', $cutoff)->delete(),
            RetentionClass::NewsletterUnsubscribed => DB::table('newsletter_subscribers')->whereNotNull('unsubscribed_at')->where('unsubscribed_at', '<', $cutoff)->delete(),
            RetentionClass::SubmissionFiles => $this->submissionFiles($cutoff),
            RetentionClass::HelpRequests => $this->helpRequests($cutoff),
            default => 0,
        };
    }

    private function operationalLogs(Carbon $cutoff): int
    {
        $count = 0;
        if (Schema::hasTable('telescope_entries')) {
            $count += DB::table('telescope_entries')->where('created_at', '<', $cutoff)->delete();
        }
        if (Schema::hasTable('failed_jobs')) {
            $count += DB::table('failed_jobs')->where('failed_at', '<', $cutoff)->delete();
        }

        return $count;
    }

    private function closedLearners(): Builder
    {
        return DB::table('users')
            ->select('id')
            ->where('status', AnonymizeAccount::STATUS)
            ->where('anonymized_at', '<=', now()->subYearsNoOverflow(self::LEARNING_RETENTION_YEARS));
    }

    private function pruneLearningRecords(): int
    {
        $count = 0;
        foreach (['lesson_progress', 'course_progress', 'live_attendance', 'quiz_attempts', 'course_completions', 'assignment_submissions'] as $table) {
            $eligible = DB::table($table)->whereIn('learner_id', $this->closedLearners());
            if ($table === 'course_completions') {
                // A completion with any certificate, including revoked certificates, must survive to preserve verification.
                $eligible->whereNotExists(DB::table('certificates')->whereColumn('course_completion_id', 'course_completions.id'));
            }
            if ($table === 'assignment_submissions') {
                // Let the file policy and disk deletion finish before a submission can cascade to its revisions.
                $eligible->whereNotExists(DB::table('submission_revisions')
                    ->join('submission_files', 'submission_files.revision_id', '=', 'submission_revisions.id')
                    ->whereColumn('submission_id', 'assignment_submissions.id'));
            }
            (clone $eligible)->select('id')->chunkById(200, function ($rows) use ($eligible, &$count): void {
                DB::transaction(function () use ($eligible, $rows, &$count): void {
                    $batch = (clone $eligible)->whereIn('id', $rows->pluck('id')->all());
                    $ids = (clone $batch)->lockForUpdate()->pluck('id')->all();
                    // Recheck references after locking the parent rows against new foreign keys.
                    $count += $batch->whereIn('id', $ids)->delete();
                });
            });
        }

        return $count;
    }

    /** Account closure sets the minimum lifetime. A longer configured file policy can extend it. */
    private function submissionFiles(?Carbon $cutoff): int
    {
        $disk = Storage::disk(Config::string('media.disk'));
        $count = 0;
        $eligible = DB::table('submission_files')
            ->join('submission_revisions', 'submission_revisions.id', '=', 'submission_files.revision_id')
            ->join('assignment_submissions', 'assignment_submissions.id', '=', 'submission_revisions.submission_id')
            ->whereIn('assignment_submissions.learner_id', $this->closedLearners());
        if ($cutoff !== null) {
            $eligible->where('submission_files.created_at', '<', $cutoff);
        }
        (clone $eligible)->select('submission_files.id')->chunkById(200, function ($rows) use ($eligible, $disk, &$count): void {
            DB::transaction(function () use ($eligible, $disk, $rows, &$count): void {
                $files = (clone $eligible)->whereIn('submission_files.id', $rows->pluck('id')->all())
                    ->lockForUpdate()->get(['submission_files.id', 'submission_files.path']);
                foreach ($files as $file) {
                    $path = (string) $file->path;
                    $shared = $path === '' || $path === Config::string('privacy.placeholder_file')
                        || DB::table('submission_files')->where('path', $path)->where('id', '!=', $file->id)->exists();
                    if (! $shared && ! $disk->delete($path) && $disk->exists($path)) {
                        throw new RuntimeException('Unable to remove expired submission attachment.');
                    }
                    $count += DB::table('submission_files')->where('id', $file->id)->delete();
                }
            });
        }, 'submission_files.id', 'id');

        return $count;
    }

    /** Closed help requests older than the cutoff, with their messages and events. Their AI messages are left to the AI class. */
    private function helpRequests(Carbon $cutoff): int
    {
        $count = 0;
        do {
            $ids = DB::table('help_requests')->whereNotNull('closed_at')->where('closed_at', '<', $cutoff)->orderBy('id')->limit(200)->pluck('id')->all();
            if ($ids === []) {
                break;
            }
            DB::table('help_request_events')->whereIn('help_request_id', $ids)->delete();
            DB::table('help_request_messages')->whereIn('help_request_id', $ids)->delete();
            $count += DB::table('help_requests')->whereIn('id', $ids)->delete();
        } while (true);

        return $count;
    }

    /** Export archives past their expiry: the file goes, the request row stays as the record that it happened. */
    private function exports(): int
    {
        $disk = Storage::disk(Config::string('media.disk'));
        $count = 0;
        $rows = DB::table('data_requests')->whereNotNull('archive_path')->where('archive_expires_at', '<', now())->get(['id', 'archive_path']);
        foreach ($rows as $row) {
            $disk->delete((string) $row->archive_path);
            $count += DB::table('data_requests')->where('id', $row->id)->update(['archive_path' => null, 'archive_sha256' => null, 'archive_size' => null]);
        }

        return $count;
    }

    /** @param  array<string, int|null>  $context */
    private function remember(string $class, int $count, array $context): void
    {
        $row = RetentionPolicy::query()->updateOrCreate(['data_class' => $class], [
            'last_pruned_at' => now(), 'last_pruned_count' => $count,
        ]);

        if ($count > 0 && $class !== RetentionClass::AiMessages->value) {
            $this->audit->record('privacy.retention_pruned', $row, null, null, ['data_class' => $class, 'removed' => $count], $context);
        }
    }
}
