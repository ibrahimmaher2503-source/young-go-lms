<?php

declare(strict_types=1);

namespace App\Domain\Settings\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Settings\PaymentsSettings;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

/** Saves the manual payment payee details (blank = fall back to the environment value). Needs system.settings.manage. The audit records only whether a value is set, never the details. */
final class SaveManualPaymentPayee
{
    public function __construct(private readonly PaymentsSettings $settings, private readonly AuditLogger $audit) {}

    /** @param  array<string, mixed>  $data */
    public function __invoke(User $actor, array $data): void
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('system.settings.manage')) {
            throw new AuthorizationException;
        }

        $clean = Validator::make($data, ['manual_payee' => ['nullable', 'string', 'max:500']], [], (array) __('settings.fields'))->validate();
        $value = is_string($clean['manual_payee'] ?? null) && trim((string) $clean['manual_payee']) !== '' ? trim((string) $clean['manual_payee']) : null;

        if ($value === $this->settings->manual_payee) {
            return;
        }

        $before = ['payee_set' => $this->settings->manual_payee !== null];

        DB::transaction(function () use ($actor, $value, $before): void {
            $this->settings->manual_payee = $value;
            $this->settings->save();

            if ($this->audit->record('settings.payee_saved', $actor, (int) $actor->getKey(), $before, ['payee_set' => $value !== null]) === null) {
                throw new RuntimeException('Unable to record payee audit.');
            }
        });
    }
}
