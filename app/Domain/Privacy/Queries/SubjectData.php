<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Queries;

use App\Domain\Entitlements\LearnerEntitlementExport;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Everything the platform holds about one account, grouped by topic, for the data-export archive. Secrets (password, 2FA,
 * tokens, link/verification hashes), payout details, ID documents, internal safeguarding records and quiz answer keys are
 * never included; `withheld()` lists the categories left out so the archive can say so.
 */
final class SubjectData
{
    public function __construct(private readonly LearnerEntitlementExport $entitlementExport = new LearnerEntitlementExport) {}

    /** Columns of `users` that belong in an export. */
    private const array USER_COLUMNS = [
        'id', 'name', 'email', 'username', 'phone', 'name_ar', 'name_en', 'display_name', 'birth_year', 'birth_month', 'timezone',
        'locale', 'status', 'email_verified_at', 'last_login_at', 'certificate_name_ar', 'certificate_name_en', 'notification_preferences',
        'created_at',
    ];

    /** @return list<string> */
    public static function withheld(): array
    {
        return ['credentials', 'two_factor', 'payout_details', 'identity_documents', 'safeguarding_records', 'quiz_answer_keys', 'audit_log', 'payment_gateway_events'];
    }

    /**
     * @return array<string, list<array<string, mixed>>> file name (without extension) => rows
     */
    public function for(int $userId): array
    {
        $user = DB::table('users')->where('id', $userId)->first(self::USER_COLUMNS);
        $email = $user === null ? null : $user->email;

        $orderIds = DB::table('orders')->where('payer_id', $userId)->pluck('id')->all();
        $attemptIds = DB::table('quiz_attempts')->where('learner_id', $userId)->pluck('id')->all();
        $submissionIds = DB::table('assignment_submissions')->where('learner_id', $userId)->pluck('id')->all();
        $revisionIds = DB::table('submission_revisions')->whereIn('submission_id', $submissionIds)->pluck('id')->all();
        $helpIds = DB::table('help_requests')->where('student_id', $userId)->pluck('id')->all();
        $conversationIds = DB::table('ai_conversations')->where('student_id', $userId)->pluck('id')->all();
        $aiMessageIds = DB::table('ai_messages')->whereIn('conversation_id', $conversationIds)->pluck('id')->all();

        return [
            'profile' => $this->rows(DB::table('users')->where('id', $userId), [], self::USER_COLUMNS),
            'consents' => $this->rows(DB::table('consents')->where('user_id', $userId)),
            'guardian_links' => $this->rows(DB::table('guardian_child_links')->where(static fn (Builder $q): Builder => $q->where('guardian_id', $userId)->orWhere('child_id', $userId)), [], ['id', 'guardian_id', 'child_id', 'status', 'method', 'verified_at', 'created_at']),
            ...$this->entitlementExport->sectionFor($userId),
            'lesson_progress' => $this->rows(DB::table('lesson_progress')->where('learner_id', $userId)),
            'course_progress' => $this->rows(DB::table('course_progress')->where('learner_id', $userId)),
            'course_completions' => $this->rows(DB::table('course_completions')->where('learner_id', $userId), ['snapshot']),
            'certificates' => $this->rows(DB::table('certificates')->where('learner_id', $userId), ['token', 'revoked_reason', 'revoked_by', 'active']),
            'quiz_attempts' => $this->rows(DB::table('quiz_attempts')->where('learner_id', $userId), ['snapshot', 'settings']),
            'quiz_answers' => $this->rows(DB::table('quiz_answers')->whereIn('quiz_attempt_id', $attemptIds), ['graded_by']),
            'assignment_submissions' => $this->rows(DB::table('assignment_submissions')->where('learner_id', $userId)),
            'submission_revisions' => $this->rows(DB::table('submission_revisions')->whereIn('submission_id', $submissionIds)),
            'submission_files' => $this->rows(DB::table('submission_files')->whereIn('revision_id', $revisionIds), ['path', 'sha256']),
            'submission_feedback' => $this->rows(DB::table('submission_feedback')->whereIn('submission_id', $submissionIds), ['author_id']),
            'orders' => $this->rows(DB::table('orders')->where('payer_id', $userId), ['idempotency_key', 'request_hash']),
            'order_items' => $this->rows(DB::table('order_items')->where(static fn (Builder $q): Builder => $q->where('learner_id', $userId)->orWhereIn('order_id', $orderIds))),
            'refunds' => $this->rows(DB::table('refunds')->whereIn('order_id', $orderIds), ['provider_transaction_id', 'provider_refund_id', 'failure_code', 'requested_by', 'approved_by', 'rejected_by', 'executed_by', 'review_note']),
            'receipts' => $this->rows(DB::table('e_receipts')->whereIn('order_id', $orderIds), ['snapshot', 'receipt_key', 'provider', 'provider_uuid', 'failure_code']),
            'help_requests' => $this->rows(DB::table('help_requests')->whereIn('id', $helpIds)),
            'help_request_messages' => $this->rows(DB::table('help_request_messages')->whereIn('help_request_id', $helpIds)),
            'ai_conversations' => $this->rows(DB::table('ai_conversations')->whereIn('id', $conversationIds)),
            'ai_messages' => $this->rows(DB::table('ai_messages')->whereIn('conversation_id', $conversationIds), ['guard_flags']),
            'ai_feedback' => $this->rows(DB::table('ai_feedback')->whereIn('message_id', $aiMessageIds)),
            'wishlist' => $this->rows(DB::table('wishlists')->where('user_id', $userId)),
            'course_reviews' => $this->rows(DB::table('course_reviews')->where('learner_id', $userId), ['moderated_by', 'moderation_reason']),
            'live_attendance' => $this->rows(DB::table('live_attendance')->where('learner_id', $userId), ['marked_by']),
            'cohort_memberships' => $this->rows(DB::table('cohort_members')->where('learner_id', $userId)),
            'notification_opt_outs' => $this->rows(DB::table('user_notification_opt_outs')->where('user_id', $userId)),
            'notifications' => $this->rows(DB::table('notifications')->where('notifiable_type', 'App\\Models\\User')->where('notifiable_id', $userId)),
            'devices' => $this->rows(DB::table('user_devices')->where('user_id', $userId), ['token_hash']),
            'sessions' => $this->rows(DB::table('user_sessions')->where('user_id', $userId), ['session_hash']),
            'instructor_applications' => $this->rows(DB::table('instructor_applications')->where('user_id', $userId), ['id_document_path', 'id_document_mime', 'id_document_sha256', 'reviewed_by', 'review_note']),
            'contact_enquiries' => $email === null ? [] : $this->rows(DB::table('contact_enquiries')->whereRaw('lower(email) = ?', [mb_strtolower((string) $email)]), ['assigned_to_id']),
            'newsletter' => $email === null ? [] : $this->rows(DB::table('newsletter_subscribers')->whereRaw('lower(email) = ?', [mb_strtolower((string) $email)])),
            'data_requests' => $this->rows(DB::table('data_requests')->where('subject_id', $userId), ['archive_path', 'archive_sha256', 'reviewed_by', 'completed_by']),
        ];
    }

    /**
     * Stored paths of the learner's submission files, keyed by file id, for copying into the archive.
     *
     * @return array<int, array{path: string, name: string}>
     */
    public function submissionFiles(int $userId): array
    {
        $files = DB::table('submission_files')
            ->join('submission_revisions', 'submission_revisions.id', '=', 'submission_files.revision_id')
            ->join('assignment_submissions', 'assignment_submissions.id', '=', 'submission_revisions.submission_id')
            ->where('assignment_submissions.learner_id', $userId)
            ->get(['submission_files.id', 'submission_files.path', 'submission_files.display_name']);

        $out = [];
        foreach ($files as $file) {
            $out[(int) $file->id] = ['path' => (string) $file->path, 'name' => (string) $file->display_name];
        }

        return $out;
    }

    /**
     * @param  Builder  $query
     * @param  list<string>  $except
     * @param  list<string>  $only
     * @return list<array<string, mixed>>
     */
    private function rows($query, array $except = [], array $only = []): array
    {
        $rows = [];
        foreach ($query->get() as $row) {
            $data = (array) $row;
            $data = $only !== [] ? Arr::only($data, $only) : Arr::except($data, $except);
            $rows[] = $data;
        }

        return $rows;
    }
}
