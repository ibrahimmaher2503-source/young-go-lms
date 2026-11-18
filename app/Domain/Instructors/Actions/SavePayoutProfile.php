<?php

declare(strict_types=1);

namespace App\Domain\Instructors\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Instructors\Models\PayoutProfile;
use App\Domain\Instructors\Queries\PublicInstructors;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * An instructor saves THEIR OWN payout destination (YG-GAP-INS-003). The details are stored encrypted in one payload. Saving
 * replaces the previous details completely. The audit entry records only who and which method: never any holder, bank or
 * account value (the logger's key redaction is a second line of defence, not the first).
 */
final class SavePayoutProfile
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param  array<string, mixed>  $data  method (bank|wallet), holder_name, bank_name?, account_number?, wallet_number? */
    public function __invoke(User $instructor, array $data): PayoutProfile
    {
        if ($instructor->getAttribute('status') !== 'active' || ! $instructor->hasAnyRole(PublicInstructors::ROLES)) {
            throw new AuthorizationException;
        }

        $method = (string) ($data['method'] ?? '');
        $details = $this->details($method, $data);

        return DB::transaction(function () use ($instructor, $method, $details): PayoutProfile {
            $profile = PayoutProfile::query()->where('user_id', $instructor->getKey())->lockForUpdate()->first() ?? new PayoutProfile(['user_id' => $instructor->getKey()]);
            $created = ! $profile->exists;
            $profile->forceFill(['method' => $method, 'details' => $details, 'updated_by' => $instructor->getKey()])->save();

            if ($this->audit->record($created ? 'payout_profile.created' : 'payout_profile.updated', $profile, (int) $instructor->getKey(), null, ['method' => $method]) === null) {
                throw new RuntimeException('Unable to record payout profile audit.');
            }

            return $profile;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function details(string $method, array $data): array
    {
        $holder = trim((string) ($data['holder_name'] ?? ''));
        if (mb_strlen($holder) < 2 || mb_strlen($holder) > 120) {
            $this->refuse('holder_name', 'holder_invalid');
        }

        if ($method === PayoutProfile::BANK) {
            $bank = trim((string) ($data['bank_name'] ?? ''));
            $account = strtoupper((string) preg_replace('/[\s-]+/', '', (string) ($data['account_number'] ?? '')));
            if (mb_strlen($bank) < 2 || mb_strlen($bank) > 120) {
                $this->refuse('bank_name', 'bank_invalid');
            }
            if (preg_match('/^[A-Z0-9]{8,34}$/', $account) !== 1) {
                $this->refuse('account_number', 'account_invalid');
            }

            return ['holder_name' => $holder, 'bank_name' => $bank, 'account_number' => $account];
        }

        if ($method === PayoutProfile::WALLET) {
            $wallet = (string) preg_replace('/[\s-]+/', '', (string) ($data['wallet_number'] ?? ''));
            if (preg_match('/^\+?[0-9]{8,15}$/', $wallet) !== 1) {
                $this->refuse('wallet_number', 'wallet_invalid');
            }

            return ['holder_name' => $holder, 'wallet_number' => $wallet];
        }

        $this->refuse('method', 'method_invalid');
    }

    private function refuse(string $field, string $key): never
    {
        throw ValidationException::withMessages([$field => __('instructor_onboarding.payout.errors.'.$key)]);
    }
}
