<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Commerce\Events\ManualPaymentProofSubmitted;
use App\Domain\Commerce\ManualPaymentProof;
use App\Domain\Commerce\ManualPaymentProofFiles;
use App\Domain\Commerce\ManualPaymentStatus;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderStatus;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\File;
use Throwable;

/**
 * The payer attaches a transfer proof to their own pending order (YG-GAP-PAY-004). The reference is normalised (trimmed,
 * upper case, no spaces) and unique across pending and approved proofs, an order holds one pending proof at a time, the
 * file is content-validated and stored privately, and the SLA clock starts. Nothing is paid or granted here.
 */
final class SubmitManualPaymentProof
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{payer_name: string, sender_account: string, transfer_reference: string, amount_minor: int, transferred_at: CarbonInterface}  $data
     */
    public function __invoke(User $payer, Order $order, array $data, File $file): ManualPaymentProof
    {
        if ($payer->getAttribute('status') !== 'active' || (int) $order->payer_id !== (int) $payer->getKey()) {
            $this->refuse('order', 'order_invalid');
        }

        $values = $this->clean($data);
        $stored = null;

        try {
            return DB::transaction(function () use ($payer, $order, $values, $file, &$stored): ManualPaymentProof {
                $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());
                if ((int) $locked->payer_id !== (int) $payer->getKey() || $locked->status !== OrderStatus::Pending || $locked->total_minor <= 0) {
                    $this->refuse('order', 'order_invalid');
                }
                if (ManualPaymentProof::query()->where('order_id', $locked->getKey())->where('status', ManualPaymentStatus::Pending->value)->exists()) {
                    $this->refuse('order', 'proof_pending');
                }
                if (ManualPaymentProof::query()->where('reference_key', $values['reference'])->exists()) {
                    $this->refuse('transfer_reference', 'reference_taken');
                }

                $stored = ManualPaymentProofFiles::store($file);

                $proof = ManualPaymentProof::query()->create([
                    'order_id' => $locked->getKey(), 'payer_id' => $payer->getKey(), 'payer_name' => $values['payer_name'],
                    'sender_account' => $values['sender_account'], 'transfer_reference' => $values['reference'], 'reference_key' => $values['reference'],
                    'active_order_id' => $locked->getKey(), 'amount_minor' => $values['amount_minor'], 'currency' => $locked->currency,
                    'transferred_at' => $values['transferred_at'], 'file_path' => $stored['path'], 'file_mime' => $stored['mime'],
                    'file_size' => $stored['size'], 'file_sha256' => $stored['sha256'], 'status' => ManualPaymentStatus::Pending->value,
                    'sla_due_at' => now()->addHours(Config::integer('manual_payments.sla_hours')),
                ]);

                $recorded = $this->audit->record('manual_payment.submitted', $proof, (int) $payer->getKey(), null, [
                    'order_id' => $locked->getKey(), 'amount_minor' => $values['amount_minor'], 'currency' => $locked->currency, 'reference' => $values['reference'],
                ]);
                if ($recorded === null) {
                    throw new RuntimeException('Unable to record manual payment audit.');
                }

                DB::afterCommit(static fn () => event(new ManualPaymentProofSubmitted($proof)));

                return $proof;
            });
        } catch (UniqueConstraintViolationException) {
            $this->discard($stored);
            $this->refuse('transfer_reference', 'reference_taken'); // a concurrent submit won the race
        } catch (Throwable $failure) {
            $this->discard($stored);
            throw $failure;
        }
    }

    /**
     * @param  array{payer_name: string, sender_account: string, transfer_reference: string, amount_minor: int, transferred_at: CarbonInterface}  $data
     * @return array{payer_name: string, sender_account: string, reference: string, amount_minor: int, transferred_at: CarbonInterface}
     */
    private function clean(array $data): array
    {
        $name = trim($data['payer_name']);
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            $this->refuse('payer_name', 'payer_name_invalid');
        }
        $sender = trim($data['sender_account']);
        if (mb_strlen($sender) < 5 || mb_strlen($sender) > 64 || preg_match('/[\x00-\x1F\x7F]/', $sender) === 1) {
            $this->refuse('sender_account', 'sender_invalid');
        }
        $reference = strtoupper((string) preg_replace('/\s+/u', '', trim($data['transfer_reference'])));
        if (preg_match('/^[A-Z0-9][A-Z0-9._\/-]{3,63}$/', $reference) !== 1) {
            $this->refuse('transfer_reference', 'reference_invalid');
        }
        if ($data['amount_minor'] <= 0) {
            $this->refuse('amount', 'amount_invalid');
        }
        $transferred = $data['transferred_at'];
        if ($transferred->gt(now()->addMinutes(5)) || $transferred->lt(now()->subDays(Config::integer('manual_payments.max_age_days')))) {
            $this->refuse('transferred_at', 'transfer_date_invalid');
        }

        return ['payer_name' => $name, 'sender_account' => $sender, 'reference' => $reference, 'amount_minor' => $data['amount_minor'], 'transferred_at' => $transferred];
    }

    /** @param  array{path: string}|null  $stored */
    private function discard(?array $stored): void
    {
        if ($stored !== null) {
            ManualPaymentProofFiles::delete($stored['path']);
        }
    }

    private function refuse(string $field, string $key): never
    {
        throw ValidationException::withMessages([$field => __('manual_payment.'.$key)]);
    }
}
