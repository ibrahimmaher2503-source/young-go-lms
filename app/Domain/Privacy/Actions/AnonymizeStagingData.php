<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Actions;

use App\Domain\Entitlements\Actions\ScrubEntitlementReasons;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Staging refresh anonymizer (YG-GAP-PRV-003). Rewrites every real name, email, phone, credential, stored file path and free-text
 * field of a copied database so no real learner (especially a minor) data remains. Refuses to run in production, and outside the
 * configured environments, no matter what flag is passed. Financial amounts, statuses and relationships are kept so the copy
 * stays realistic; the buyer snapshot of receipts and transfer-proof payer fields are rewritten here because staging is not
 * a legal record. Idempotent.
 */
final class AnonymizeStagingData
{
    private const string EMAIL_PATTERN = '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i';

    /** International or local phone shapes; dates and plain ids do not match. */
    private const string PHONE_PATTERN = '/(?<![\w.])\+?\d{1,3}[\s.-]?\(?\d{2,4}\)?[\s.-]?\d{3,4}[\s.-]?\d{3,4}(?!\w)/';

    /** Free-text columns scrubbed of emails and phone numbers: table => columns. */
    private const array FREE_TEXT = [
        'help_requests' => ['question', 'ai_answer'],
        'help_request_messages' => ['body'],
        'ai_messages' => ['content'],
        'submission_revisions' => ['text_body'],
        'submission_feedback' => ['body', 'feedback'],
        'course_reviews' => ['body'],
        'refunds' => ['reason', 'review_note'],
        'activity_log' => ['properties'],
    ];

    public function __construct(private readonly ScrubEntitlementReasons $scrubEntitlementReasons = new ScrubEntitlementReasons) {}

    /** @return array<string, int> rows rewritten or removed, by area */
    public function __invoke(?string $password = null): array
    {
        $this->assertAllowedEnvironment();

        $report = [];
        $placeholder = $this->placeholder();
        $hash = Hash::make($password ?? Str::random(40));

        $report['users'] = $this->users($hash);
        $report['contact_enquiries'] = $this->each('contact_enquiries', static fn (object $row): array => [
            'name' => 'Enquirer '.$row->id, 'email' => 'enquiry'.$row->id.'@anonymized.invalid', 'phone' => null, 'message' => self::scrub((string) $row->message),
        ]);
        $report['newsletter_subscribers'] = $this->each('newsletter_subscribers', static fn (object $row): array => ['email' => 'subscriber'.$row->id.'@anonymized.invalid']);
        $report['instructor_applications'] = $this->each('instructor_applications', static fn (object $row): array => [
            'full_name' => 'Applicant '.$row->id, 'phone' => null, 'bio' => self::scrub((string) $row->bio), 'portfolio_links' => '[]',
            'id_document_path' => $placeholder['path'], 'id_document_sha256' => $placeholder['sha256'],
        ]);
        $report['payout_profiles'] = DB::table('instructor_payout_profiles')->delete();
        $report['manual_payment_proofs'] = $this->each('manual_payment_proofs', static fn (object $row): array => [
            'payer_name' => 'Payer '.$row->payer_id, 'sender_account' => 'XXXX', 'transfer_reference' => 'ANON-'.$row->id,
            'file_path' => $placeholder['path'], 'file_sha256' => $placeholder['sha256'], 'file_size' => $placeholder['size'],
        ]);
        $report['receipts'] = $this->each('e_receipts', static function (object $row): array {
            $snapshot = json_decode((string) $row->snapshot, true);
            if (is_array($snapshot) && isset($snapshot['buyer'])) {
                $snapshot['buyer'] = ['name' => 'Anonymized buyer', 'email' => 'buyer@anonymized.invalid'];
            }

            return ['snapshot' => (string) json_encode($snapshot)];
        });
        $report['certificates'] = $this->each('certificates', static fn (object $row): array => [
            'learner_name' => 'Learner '.$row->learner_id, 'learner_name_ar' => 'Learner '.$row->learner_id, 'learner_name_en' => 'Learner '.$row->learner_id,
        ]);
        $report['course_reviews'] = $this->each('course_reviews', static fn (object $row): array => ['author_name' => 'Learner']);
        $report['submission_files'] = $this->each('submission_files', static fn (object $row): array => [
            'display_name' => 'file-'.$row->id, 'path' => $placeholder['path'], 'sha256' => $placeholder['sha256'], 'size' => $placeholder['size'],
        ]);
        $report['devices'] = DB::table('user_devices')->update(['label' => null, 'last_ip' => null]);
        $report['user_sessions'] = DB::table('user_sessions')->update(['ip' => null, 'user_agent' => null]);

        $scrubbed = 0;
        foreach (self::FREE_TEXT as $table => $columns) {
            foreach ($columns as $column) {
                $scrubbed += $this->scrubColumn($table, $column);
            }
        }
        $scrubbed += ($this->scrubEntitlementReasons)(self::scrub(...));
        $report['free_text'] = $scrubbed;

        $cleared = 0;
        foreach (['password_reset_tokens', 'sessions', 'notifications', 'jobs', 'failed_jobs', 'telescope_entries', 'telescope_entries_tags', 'telescope_monitoring'] as $table) {
            if (Schema::hasTable($table)) {
                $cleared += DB::table($table)->delete();
            }
        }
        $report['cleared'] = $cleared;

        return $report;
    }

    public static function scrub(string $text): string
    {
        $text = (string) preg_replace(self::EMAIL_PATTERN, 'redacted@anonymized.invalid', $text);

        return (string) preg_replace(self::PHONE_PATTERN, '[phone]', $text);
    }

    private function assertAllowedEnvironment(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException(__('privacy.anonymizer_production'));
        }
        if (! app()->environment(Config::array('privacy.anonymizer_environments'))) {
            throw new RuntimeException(__('privacy.anonymizer_environment'));
        }
    }

    private function users(string $hash): int
    {
        $count = 0;
        DB::table('users')->orderBy('id')->chunkById(500, function ($rows) use ($hash, &$count): void {
            foreach ($rows as $row) {
                DB::table('users')->where('id', $row->id)->update([
                    'name' => 'User '.$row->id, 'email' => 'user'.$row->id.'@anonymized.invalid', 'username' => 'user'.$row->id,
                    'phone' => null, 'name_ar' => null, 'name_en' => null, 'display_name' => null, 'birth_year' => null, 'birth_month' => null,
                    'avatar_path' => null, 'notification_preferences' => null, 'phone_reminder_opt_ins' => null,
                    'certificate_name_ar' => null, 'certificate_name_en' => null, 'password' => $hash, 'remember_token' => null,
                    'two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null,
                ]);
                $count++;
            }
        });

        return $count;
    }

    /**
     * @param  callable(object): array<string, mixed>  $changes
     */
    private function each(string $table, callable $changes): int
    {
        $count = 0;
        DB::table($table)->orderBy('id')->chunkById(500, function ($rows) use ($table, $changes, &$count): void {
            foreach ($rows as $row) {
                DB::table($table)->where('id', $row->id)->update($changes($row));
                $count++;
            }
        });

        return $count;
    }

    private function scrubColumn(string $table, string $column): int
    {
        $count = 0;
        DB::table($table)->whereNotNull($column)->orderBy('id')->chunkById(500, function ($rows) use ($table, $column, &$count): void {
            foreach ($rows as $row) {
                $value = (string) $row->{$column};
                $clean = self::scrub($value);
                if ($clean !== $value) {
                    DB::table($table)->where('id', $row->id)->update([$column => $clean]);
                    $count++;
                }
            }
        });

        return $count;
    }

    /** @return array{path: string, sha256: string, size: int} */
    private function placeholder(): array
    {
        $disk = Storage::disk(Config::string('media.disk'));
        $path = Config::string('privacy.placeholder_file');
        $body = 'Staging placeholder: the original file was removed by app:anonymize.';
        if (! $disk->exists($path)) {
            $disk->put($path, $body);
        }

        return ['path' => $path, 'sha256' => hash('sha256', $body), 'size' => strlen($body)];
    }
}
