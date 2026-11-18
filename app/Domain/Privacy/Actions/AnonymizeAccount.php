<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Commerce\Refund;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Account deletion = anonymization in place (YG-GAP-PRV-001). The users row stays (orders, refunds, receipts, ledger rows, consents
 * and the audit log point at it) but every personal field, credential, session, device, notification, wishlist, review, AI
 * conversation and submission text/file is removed or blanked. Certificates keep their number and token so public verification
 * still answers, with the holder's name replaced and `anonymized_at` set. Financial records are NEVER edited here: orders,
 * payments, refunds, e-receipts (including the buyer snapshot) and transfer proofs are retained as legal/financial evidence until
 * O-025 sets final periods. Safeguarding flags stay under their own policy (O-028). Idempotent: an anonymized account is a no-op.
 *
 * Refused for: accounts with staff/instructor roles (offboard first), a payer with an open refund, a guardian with active
 * verified children (unlink or anonymize the children first).
 */
final class AnonymizeAccount
{
    public const string STATUS = 'anonymized';

    private const string REMOVED = '[removed]';

    public function __construct(private readonly AuditLogger $audit) {}

    /** @return array<string, int> what was removed or blanked, by category */
    public function __invoke(User $actor, User $subject): array
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('privacy.manage')) {
            throw new AuthorizationException;
        }

        $files = ['local' => [], 'public' => []];
        $report = DB::transaction(function () use ($actor, $subject, &$files): ?array {
            $locked = User::query()->lockForUpdate()->findOrFail($subject->getKey());
            if ($locked->getAttribute('status') === self::STATUS) {
                return null;
            }
            $this->assertEligible($locked);

            $report = $this->scrub($locked, $files);

            if ($this->audit->record('privacy.account_anonymized', $locked, (int) $actor->getKey(), null, ['status' => self::STATUS], ['removed' => $report]) === null) {
                throw new RuntimeException('Unable to record anonymization audit.');
            }

            return $report;
        });

        if ($report === null) {
            return [];
        }

        // Files go after the commit: a rolled-back anonymization must not lose them.
        $media = Storage::disk(Config::string('media.disk'));
        foreach ($files['local'] as $path) {
            $media->delete($path);
        }
        foreach ($files['public'] as $path) {
            Storage::disk('public')->delete($path);
        }

        return $report;
    }

    private function assertEligible(User $user): void
    {
        $allowed = Config::array('privacy.self_service_roles');
        if ($user->getRoleNames()->contains(static fn (string $role): bool => ! in_array($role, $allowed, true))) {
            throw ValidationException::withMessages(['account' => __('privacy.error_staff_account')]);
        }

        $openRefund = Refund::query()->whereHas('order', static fn ($q) => $q->where('payer_id', $user->getKey()))
            ->whereIn('status', ['requested', 'approved', 'processing'])->exists();
        if ($openRefund) {
            throw ValidationException::withMessages(['account' => __('privacy.error_open_refund')]);
        }

        $children = GuardianChildLink::query()->where('guardian_id', $user->getKey())->where('status', GuardianChildLink::STATUS_VERIFIED)
            ->whereIn('child_id', User::query()->where('status', 'active')->select('id'))->exists();
        if ($children) {
            throw ValidationException::withMessages(['account' => __('privacy.error_active_children')]);
        }
    }

    /**
     * @param  array{local: list<string>, public: list<string>}  $files
     * @return array<string, int>
     */
    private function scrub(User $user, array &$files): array
    {
        $id = (int) $user->getKey();
        $email = $user->getAttribute('email');
        $report = [];

        // Sessions, devices, inbox and preferences.
        $report['sessions'] = DB::table('sessions')->where('user_id', $id)->delete() + DB::table('user_sessions')->where('user_id', $id)->delete();
        $report['devices'] = DB::table('user_devices')->where('user_id', $id)->delete();
        $report['notifications'] = DB::table('notifications')->where('notifiable_type', User::class)->where('notifiable_id', $id)->delete();
        $report['notification_opt_outs'] = DB::table('user_notification_opt_outs')->where('user_id', $id)->delete();
        $report['wishlist'] = DB::table('wishlists')->where('user_id', $id)->delete();
        $report['reviews'] = DB::table('course_reviews')->where('learner_id', $id)->delete();
        if (is_string($email) && $email !== '') {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
        }

        // Enquiries and newsletter rows that carry the same email.
        $report['enquiries'] = 0;
        $report['newsletter'] = 0;
        if (is_string($email) && $email !== '') {
            $lower = mb_strtolower($email);
            $report['enquiries'] = DB::table('contact_enquiries')->whereRaw('lower(email) = ?', [$lower])->update([
                'name' => self::REMOVED, 'email' => 'anonymized-'.$id.'@anonymized.invalid', 'phone' => null, 'message' => self::REMOVED,
            ]);
            $report['newsletter'] = DB::table('newsletter_subscribers')->whereRaw('lower(email) = ?', [$lower])->delete();
        }

        // Pending instructor application: personal details and the ID document go.
        $applications = DB::table('instructor_applications')->where('user_id', $id)->get(['id', 'id_document_path']);
        foreach ($applications as $application) {
            if (is_string($application->id_document_path) && $application->id_document_path !== '') {
                $files['local'][] = $application->id_document_path;
            }
        }
        $report['applications'] = DB::table('instructor_applications')->where('user_id', $id)->update([
            'full_name' => self::REMOVED, 'phone' => null, 'bio' => self::REMOVED, 'portfolio_links' => '[]',
            'id_document_path' => '', 'id_document_sha256' => '',
        ]);

        // Learner work: essay text and uploaded files. Grades, decisions and reviewer feedback stay as the learning record.
        $submissionIds = DB::table('assignment_submissions')->where('learner_id', $id)->pluck('id')->all();
        $revisionIds = DB::table('submission_revisions')->whereIn('submission_id', $submissionIds)->pluck('id')->all();
        $report['submission_text'] = DB::table('submission_revisions')->whereIn('id', $revisionIds)->whereNotNull('text_body')->update(['text_body' => null]);
        foreach (DB::table('submission_files')->whereIn('revision_id', $revisionIds)->pluck('path') as $path) {
            $files['local'][] = (string) $path;
        }
        $report['submission_files'] = DB::table('submission_files')->whereIn('revision_id', $revisionIds)->delete();
        $attemptIds = DB::table('quiz_attempts')->where('learner_id', $id)->pluck('id')->all();
        $report['essay_answers'] = DB::table('quiz_answers')->whereIn('quiz_attempt_id', $attemptIds)->where('answer', 'like', '%"text"%')->update(['answer' => null]);

        // Help requests and AI conversations (T-P7-04 / T-P7-06 data).
        $helpIds = DB::table('help_requests')->where('student_id', $id)->pluck('id')->all();
        $report['help_requests'] = DB::table('help_requests')->whereIn('id', $helpIds)->update(['question' => self::REMOVED, 'ai_answer' => self::REMOVED]);
        $report['help_messages'] = DB::table('help_request_messages')->whereIn('help_request_id', $helpIds)->where('author_id', $id)->update(['body' => self::REMOVED]);

        $conversationIds = DB::table('ai_conversations')->where('student_id', $id)->pluck('id')->all();
        $pinnedMessages = DB::table('help_requests')->whereIn('conversation_id', $conversationIds)->pluck('ai_message_id')->all();
        $messageIds = DB::table('ai_messages')->whereIn('conversation_id', $conversationIds)->pluck('id')->all();
        DB::table('ai_feedback')->whereIn('message_id', $messageIds)->delete();
        $report['ai_messages'] = DB::table('ai_messages')->whereIn('id', $messageIds)->whereNotIn('id', $pinnedMessages)->delete();
        $report['ai_messages_blanked'] = DB::table('ai_messages')->whereIn('id', $pinnedMessages)->update(['content' => self::REMOVED]);
        $pinnedConversations = array_merge(
            DB::table('help_requests')->whereIn('conversation_id', $conversationIds)->pluck('conversation_id')->all(),
            DB::table('ai_safeguarding_flags')->whereIn('conversation_id', $conversationIds)->pluck('conversation_id')->all(),
        );
        $report['ai_conversations'] = DB::table('ai_conversations')->whereIn('id', $conversationIds)->whereNotIn('id', $pinnedConversations)->delete();
        $report['ai_quota'] = DB::table('ai_quota_counters')->where('student_id', $id)->delete();

        // Certificates stay verifiable but lose the holder's name.
        $report['certificates'] = DB::table('certificates')->where('learner_id', $id)->update([
            'learner_name' => __('privacy.anonymized_learner', [], 'en'),
            'learner_name_ar' => __('privacy.anonymized_learner', [], 'ar'),
            'learner_name_en' => __('privacy.anonymized_learner', [], 'en'),
            'anonymized_at' => now(),
        ]);

        // Avatar files (private copy and public copy).
        $avatar = $user->getAttribute('avatar_path');
        if (is_string($avatar) && $avatar !== '') {
            $files['local'][] = $avatar;
            $files['public'][] = $avatar;
        }

        // Finally the account row itself: no credentials, no contact data, no access.
        DB::table('users')->where('id', $id)->update([
            'name' => 'Anonymized user #'.$id,
            'email' => null,
            'username' => null,
            'phone' => null,
            'name_ar' => null,
            'name_en' => null,
            'display_name' => null,
            'birth_year' => null,
            'birth_month' => null,
            'avatar_path' => null,
            'notification_preferences' => null,
            'phone_reminder_opt_ins' => null,
            'certificate_name_ar' => null,
            'certificate_name_en' => null,
            'password' => Hash::make(Str::random(64)),
            'remember_token' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'email_verified_at' => null,
            'last_login_at' => null,
            'status' => self::STATUS,
            'anonymized_at' => now(),
            'updated_at' => now(),
        ]);
        $report['account'] = 1;

        return $report;
    }
}
