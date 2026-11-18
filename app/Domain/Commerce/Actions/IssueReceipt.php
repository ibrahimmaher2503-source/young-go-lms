<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Commerce\EReceipt;
use App\Domain\Commerce\EReceiptStatus;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\PaymentEvent;
use App\Domain\Commerce\PaymentEventOutcome;
use App\Domain\Commerce\ReceiptKind;
use App\Domain\Commerce\Refund;
use App\Domain\Commerce\RefundStatus;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Issues the receipt of a paid order (sale) or of an executed refund (credit note). Idempotent: one receipt per order sale and
 * per refund, however often it is asked for. The number derives from the order or refund reference (no sequence to race). The
 * snapshot holds the ETA-ready fields (seller, buyer, lines, totals, tax) frozen at issue; seller tax data and the tax amount
 * stay null while they are UNKNOWN (O-023). Status stays `not_submitted`: nothing is sent anywhere here.
 */
final class IssueReceipt
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(Order $order, ?Refund $refund = null): EReceipt
    {
        $kind = $refund === null ? ReceiptKind::Sale : ReceiptKind::Refund;
        if ($refund === null && ($order->paid_at === null || ! in_array($order->status, [OrderStatus::Paid, OrderStatus::PartiallyRefunded, OrderStatus::Refunded, OrderStatus::Disputed], true))) {
            $this->refuse();
        }
        if ($refund !== null && ((int) $refund->order_id !== (int) $order->getKey() || $refund->status !== RefundStatus::Executed)) {
            $this->refuse();
        }

        $key = $refund === null ? 'sale:'.$order->getKey() : 'refund:'.$refund->getKey();
        $existing = EReceipt::query()->where('receipt_key', $key)->first();
        if ($existing !== null) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($order, $refund, $kind, $key): EReceipt {
                $receipt = $refund === null ? $this->sale($order, $key) : $this->creditNote($order, $refund, $key);

                $recorded = $this->audit->record('e_receipt.issued', $receipt, null, null, [
                    'number' => $receipt->number, 'kind' => $kind->value, 'order_id' => $order->getKey(), 'total_minor' => $receipt->total_minor,
                ]);
                if ($recorded === null) {
                    throw new RuntimeException('Unable to record receipt audit.');
                }

                return $receipt;
            });
        } catch (UniqueConstraintViolationException) {
            return EReceipt::query()->where('receipt_key', $key)->firstOrFail(); // a concurrent request issued it first
        }
    }

    private function sale(Order $order, string $key): EReceipt
    {
        $lines = [];
        foreach ($order->items()->orderBy('id')->get() as $item) {
            $lines[] = [
                'title_ar' => $item->title_ar, 'title_en' => $item->title_en, 'quantity' => 1,
                'unit_price_minor' => $item->unit_price_minor, 'discount_minor' => $item->discount_minor,
                'net_minor' => $item->allocated_minor ?? ($item->unit_price_minor - $item->discount_minor),
            ];
        }

        return $this->store($order, null, $key, 'RC-'.$order->reference, ReceiptKind::Sale, $order->subtotal_minor, $order->discount_minor, $order->total_minor, $lines, $order->paid_at?->toIso8601String());
    }

    private function creditNote(Order $order, Refund $refund, string $key): EReceipt
    {
        $lines = [[
            'title_ar' => __('refunds.line_title', ['order' => $order->reference], 'ar'),
            'title_en' => __('refunds.line_title', ['order' => $order->reference], 'en'),
            'quantity' => 1, 'unit_price_minor' => $refund->amount_minor, 'discount_minor' => 0, 'net_minor' => $refund->amount_minor,
        ]];

        return $this->store($order, $refund, $key, 'CN-'.$refund->reference, ReceiptKind::Refund, $refund->amount_minor, 0, $refund->amount_minor, $lines, $refund->executed_at?->toIso8601String());
    }

    /** @param  list<array<string, mixed>>  $lines */
    private function store(Order $order, ?Refund $refund, string $key, string $number, ReceiptKind $kind, int $subtotal, int $discount, int $total, array $lines, ?string $transactedAt): EReceipt
    {
        $order->loadMissing('payer');
        $payer = $order->payer;
        $gateway = PaymentEvent::query()->where('order_id', $order->getKey())->where('outcome', PaymentEventOutcome::Fulfilled->value)->value('gateway');

        $snapshot = [
            'document_type' => $kind === ReceiptKind::Sale ? 'receipt' : 'credit_note',
            'seller' => [
                'name' => config('e_receipts.seller.name'), 'tax_id' => config('e_receipts.seller.tax_id'),
                'branch_code' => config('e_receipts.seller.branch_code'), 'address' => config('e_receipts.seller.address'),
            ],
            'buyer' => ['name' => $payer?->getAttribute('name'), 'email' => $payer?->getAttribute('email')],
            'lines' => $lines,
            'totals' => ['currency' => $order->currency, 'subtotal_minor' => $subtotal, 'discount_minor' => $discount, 'tax_minor' => null, 'total_minor' => $total],
            'payment' => ['method' => is_string($gateway) ? $gateway : 'manual_transfer', 'order_reference' => $order->reference],
            'original_receipt_number' => $kind === ReceiptKind::Refund ? 'RC-'.$order->reference : null,
            'transacted_at' => $transactedAt,
        ];

        return EReceipt::query()->create([
            'receipt_key' => $key, 'number' => $number, 'kind' => $kind->value, 'order_id' => $order->getKey(), 'refund_id' => $refund?->getKey(),
            'currency' => $order->currency, 'subtotal_minor' => $subtotal, 'discount_minor' => $discount, 'tax_minor' => null, 'total_minor' => $total,
            'status' => EReceiptStatus::NotSubmitted->value, 'snapshot' => $snapshot, 'issued_at' => now(),
        ]);
    }

    private function refuse(): never
    {
        throw ValidationException::withMessages(['receipt' => __('refunds.receipt_unavailable')]);
    }
}
