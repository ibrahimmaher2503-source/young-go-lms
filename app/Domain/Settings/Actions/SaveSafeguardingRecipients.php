<?php

declare(strict_types=1);

namespace App\Domain\Settings\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Settings\AiSettings;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * O-028: saves the extra people told about safeguarding cases, as email addresses, in `ai.safeguarding_extra_recipients`.
 * Needs `system.settings.manage` (admin, super_admin). Emails are trimmed, lowercased, deduplicated and capped. Numeric user ids
 * that an earlier version of the setting may hold are kept as they are. Audited with before and after lists.
 */
final class SaveSafeguardingRecipients
{
    public const int MAX_EMAILS = 20;

    public function __construct(private readonly AiSettings $settings, private readonly AuditLogger $audit) {}

    /** @return list<string> the emails currently stored (ids are not listed) */
    public function emails(): array
    {
        return $this->split()['emails'];
    }

    /** @param  list<string>|string|null  $emails  addresses, or free text separated by commas, semicolons, spaces or new lines */
    public function __invoke(User $actor, array|string|null $emails): void
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('system.settings.manage')) {
            throw new AuthorizationException;
        }

        $entries = is_array($emails) ? $emails : (preg_split('/[\s,;]+/', (string) $emails, -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $clean = [];
        foreach ($entries as $entry) {
            $value = mb_strtolower(trim((string) $entry));
            if ($value === '') {
                continue;
            }
            if (filter_var($value, FILTER_VALIDATE_EMAIL) === false || mb_strlen($value) > 190) {
                throw ValidationException::withMessages(['emails' => __('settings.safeguarding.error_email', ['email' => $value])]);
            }
            $clean[$value] = $value;
        }
        $clean = array_values($clean);
        if (count($clean) > self::MAX_EMAILS) {
            throw ValidationException::withMessages(['emails' => __('settings.safeguarding.error_max', ['max' => self::MAX_EMAILS])]);
        }

        $current = $this->split();
        if ($current['emails'] === $clean) {
            return;
        }

        $tokens = [...array_map('strval', $current['ids']), ...$clean];

        DB::transaction(function () use ($actor, $tokens, $current, $clean): void {
            $this->settings->safeguarding_extra_recipients = $tokens === [] ? null : implode(', ', $tokens);
            $this->settings->save();

            if ($this->audit->record('settings.safeguarding_recipients_saved', $actor, (int) $actor->getKey(), ['emails' => $current['emails']], ['emails' => $clean]) === null) {
                throw new RuntimeException('Unable to record safeguarding recipients audit.');
            }
        });
    }

    /** @return array{ids: list<int>, emails: list<string>} */
    private function split(): array
    {
        $ids = [];
        $emails = [];
        foreach (preg_split('/[\s,;]+/', (string) $this->settings->safeguarding_extra_recipients, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $entry) {
            if (ctype_digit($entry)) {
                $ids[] = (int) $entry;
            } elseif (filter_var($entry, FILTER_VALIDATE_EMAIL) !== false) {
                $emails[] = mb_strtolower($entry);
            }
        }

        return ['ids' => $ids, 'emails' => array_values(array_unique($emails))];
    }
}
