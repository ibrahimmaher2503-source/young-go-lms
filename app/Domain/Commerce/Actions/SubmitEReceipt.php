<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Commerce\Contracts\EReceiptProvider;
use App\Domain\Commerce\EReceipt;
use App\Domain\Commerce\EReceiptStatus;
use App\Domain\Commerce\Receipts\EReceiptNotEnabled;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Sends one receipt through the configured e-receipt provider. With the default (null) driver this refuses and changes nothing:
 * the adapter is disabled until O-023 closes and no submission is ever faked. Staff need payment.approve_manual.
 */
final class SubmitEReceipt
{
    public function __construct(private readonly AuditLogger $audit, private readonly EReceiptProvider $provider) {}

    public function __invoke(User $actor, EReceipt $receipt): EReceipt
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('payment.approve_manual')) {
            throw new AuthorizationException;
        }
        if (! $this->provider->isEnabled()) {
            throw ValidationException::withMessages(['e_receipt' => __('refunds.e_receipt_disabled')]);
        }

        return DB::transaction(function () use ($actor, $receipt): EReceipt {
            $locked = EReceipt::query()->lockForUpdate()->findOrFail($receipt->getKey());
            if ($locked->status === EReceiptStatus::Submitted) {
                return $locked;
            }

            try {
                $result = $this->provider->submit($locked);
            } catch (EReceiptNotEnabled) {
                throw ValidationException::withMessages(['e_receipt' => __('refunds.e_receipt_disabled')]);
            }

            $locked->forceFill([
                'status' => EReceiptStatus::Submitted->value, 'provider' => $result->provider, 'provider_uuid' => $result->documentUuid,
                'submitted_at' => now(), 'failure_code' => null,
            ])->save();

            $recorded = $this->audit->record('e_receipt.submitted', $locked, (int) $actor->getKey(), ['status' => EReceiptStatus::NotSubmitted->value], ['status' => EReceiptStatus::Submitted->value, 'provider' => $result->provider]);
            if ($recorded === null) {
                throw new RuntimeException('Unable to record receipt audit.');
            }

            return $locked;
        });
    }
}
