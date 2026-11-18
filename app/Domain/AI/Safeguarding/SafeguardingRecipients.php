<?php

declare(strict_types=1);

namespace App\Domain\AI\Safeguarding;

use App\Domain\Settings\AiSettings;
use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

/**
 * O-028: the designated safeguarding group. Active users holding one of the configured roles (config `safeguarding.recipient_roles`,
 * default admin and support) plus the optional specific ids or emails from the AI settings. Unknown roles are skipped.
 */
final class SafeguardingRecipients
{
    public function __construct(private readonly AiSettings $settings) {}

    /** @return Collection<int, User> */
    public function all(): Collection
    {
        $users = new Collection;

        $configured = config('safeguarding.recipient_roles', ['admin', 'support']);
        $roles = is_array($configured) ? array_values(array_filter($configured, is_string(...))) : [];
        $existing = $roles === [] ? [] : Role::query()->whereIn('name', $roles)->pluck('name')->all();
        if ($existing !== []) {
            $users = $users->merge(User::query()->role($existing)->where('status', 'active')->get());
        }

        [$ids, $emails] = $this->extras();
        if ($ids !== [] || $emails !== []) {
            $users = $users->merge(User::query()->where('status', 'active')->where(static function ($query) use ($ids, $emails): void {
                $query->whereIn('id', $ids)->orWhereIn('email', $emails);
            })->get());
        }

        /** @var Collection<int, User> $unique */
        $unique = $users->unique(static fn (User $user): int => (int) $user->getKey())->values();

        return $unique;
    }

    /** @return array{0: list<int>, 1: list<string>} */
    private function extras(): array
    {
        $raw = $this->settings->safeguarding_extra_recipients;

        $ids = [];
        $emails = [];
        foreach (preg_split('/[\s,;]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $entry) {
            if (ctype_digit($entry)) {
                $ids[] = (int) $entry;
            } elseif (filter_var($entry, FILTER_VALIDATE_EMAIL) !== false) {
                $emails[] = mb_strtolower($entry);
            }
        }

        return [$ids, $emails];
    }
}
