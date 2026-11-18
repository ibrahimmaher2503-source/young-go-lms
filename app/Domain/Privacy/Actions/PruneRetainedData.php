<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Actions;

use App\Domain\AI\Admin\Actions\PruneAiData;
use App\Domain\Audit\AuditLogger;
use App\Domain\Privacy\ProtectedData;
use App\Domain\Privacy\RetentionClass;
use App\Domain\Privacy\RetentionPolicies;
use App\Domain\Privacy\RetentionPolicy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Scheduled retention pruning (YG-GAP-PRV-002). Each class deletes only rows older than its configured days; a class with no
 * value keeps its data. Every class is checked against ProtectedData first, so orders, payments, refunds, receipts, certificates,
 * consents and the audit log can never be reached. Help requests run before AI messages because help requests pin AI messages.
 * Idempotent; safe to run daily.
 */
final class PruneRetainedData
{
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
            $this->remember($class, $count);
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

    /** Files of graded work older than the cutoff: the stored file and its row go; the submission, grade and feedback stay. */
    private function submissionFiles(Carbon $cutoff): int
    {
        $disk = Storage::disk(Config::string('media.disk'));
        $count = 0;
        do {
            $rows = DB::table('submission_files')
                ->join('submission_revisions', 'submission_revisions.id', '=', 'submission_files.revision_id')
                ->join('assignment_submissions', 'assignment_submissions.id', '=', 'submission_revisions.submission_id')
                ->where('assignment_submissions.status', 'graded')
                ->where('submission_files.created_at', '<', $cutoff)
                ->orderBy('submission_files.id')->limit(200)
                ->get(['submission_files.id', 'submission_files.path']);
            if ($rows->isEmpty()) {
                break;
            }
            foreach ($rows as $row) {
                $disk->delete((string) $row->path);
            }
            $count += DB::table('submission_files')->whereIn('id', $rows->pluck('id')->all())->delete();
        } while (true);

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

    private function remember(RetentionClass $class, int $count): void
    {
        $row = RetentionPolicy::query()->firstOrNew(['data_class' => $class->value]);
        $row->forceFill(['last_pruned_at' => now(), 'last_pruned_count' => $count])->save();

        if ($count > 0 && $class !== RetentionClass::AiMessages) {
            $this->audit->record('privacy.retention_pruned', $row, null, null, ['data_class' => $class->value, 'removed' => $count], ['retention_days' => $this->policies->days($class)]);
        }
    }
}
