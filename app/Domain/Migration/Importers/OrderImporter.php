<?php

declare(strict_types=1);

namespace App\Domain\Migration\Importers;

use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderItem;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\ProductType;
use App\Domain\Migration\Data\ImportContext;
use App\Domain\Migration\Data\ImportExceptionData;
use App\Domain\Migration\Data\RowImportResult;
use App\Domain\Migration\Enums\LegacyEntity;
use App\Domain\Migration\Enums\MigrationExceptionSeverity;
use App\Domain\Migration\Support\LegacyDateParser;
use App\Models\Course;

/**
 * Imports historical paid transactions as read-only Order + OrderItem history (no PaymentVerified or ledger side effects).
 */
final class OrderImporter extends AbstractEntityImporter
{
    public function entity(): LegacyEntity
    {
        return LegacyEntity::Orders;
    }

    public function sourceTable(): string
    {
        return 'payment';
    }

    public function checksumPayload(array $row): array
    {
        return [
            'id' => $row['id'] ?? null,
            'user_id' => $row['user_id'] ?? null,
            'payment_type' => $row['payment_type'] ?? null,
            'course_id' => $row['course_id'] ?? null,
            'amount' => $row['amount'] ?? null,
            'tax' => $row['tax'] ?? null,
            'transaction_id' => $row['transaction_id'] ?? null,
            'coupon' => $row['coupon'] ?? null,
            'date_added' => $row['date_added'] ?? null,
        ];
    }

    public function importRow(array $row, ImportContext $context): RowImportResult
    {
        $sourceId = $this->extractSourceId($row);
        $legacyUserId = (int) ($row['user_id'] ?? 0);
        $legacyCourseId = (int) ($row['course_id'] ?? 0);

        $payerId = $context->mappedId(LegacyEntity::Users, $legacyUserId);
        if ($payerId === null) {
            return RowImportResult::rejected('missing_user', "Legacy payer {$legacyUserId} is not mapped.");
        }

        $courseId = $context->mappedId(LegacyEntity::Courses, $legacyCourseId);
        if ($courseId === null) {
            return RowImportResult::rejected('missing_course', "Legacy course {$legacyCourseId} is not mapped.");
        }

        $rawAmount = $row['amount'] ?? null;
        if (! is_numeric($rawAmount) || (float) $rawAmount < 0) {
            return RowImportResult::rejected('invalid_amount', 'Legacy payment amount is missing or negative.');
        }

        $amountMinor = (int) round((float) $rawAmount * 100);
        $paidAt = LegacyDateParser::toUtc($row['date_added'] ?? null) ?? now()->utc();
        $reference = 'LEG-'.str_pad($sourceId, 10, '0', STR_PAD_LEFT);
        $idempotencyKey = 'legacy:payment:'.$sourceId;

        $metadata = [
            'reference' => $reference,
            'amount_minor' => $amountMinor,
            'payment_type' => $row['payment_type'] ?? null,
            'transaction_id' => $row['transaction_id'] ?? null,
            'coupon' => $row['coupon'] ?? null,
        ];

        $existingTargetId = $context->existingRecord?->target_id;
        if ($context->existingRecord !== null && $existingTargetId !== null && $context->existingRecord->checksum !== $context->checksum) {
            return RowImportResult::skipped(
                'paid_order_changed_in_source',
                'Legacy payment source changed after import; paid Order money columns are frozen and require finance review.',
                Order::class,
                $existingTargetId,
                $metadata,
                [
                    new ImportExceptionData(
                        'paid_order_changed_in_source',
                        MigrationExceptionSeverity::DecisionRequired,
                        "Legacy payment {$sourceId} changed in source after initial import.",
                        $metadata,
                    ),
                ],
            );
        }

        if ($context->dryRun) {
            $shadowId = $context->idMap->rememberShadow(LegacyEntity::Orders, $sourceId, Order::class, $existingTargetId, $metadata);

            return $existingTargetId !== null
                ? RowImportResult::unchanged(Order::class, $shadowId, $metadata)
                : RowImportResult::imported(Order::class, $shadowId, $metadata);
        }

        $existing = Order::query()->where('payer_id', $payerId)->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return RowImportResult::unchanged(Order::class, (int) $existing->getKey(), $metadata);
        }

        $course = Course::query()->find($courseId);
        if ($course === null) {
            return RowImportResult::rejected('missing_course', "Target course {$courseId} not found.");
        }

        $order = Order::query()->create([
            'reference' => $reference,
            'payer_id' => $payerId,
            'market' => $context->defaultMarket,
            'currency' => $context->defaultCurrency,
            'subtotal_minor' => $amountMinor,
            'discount_minor' => 0,
            'total_minor' => $amountMinor,
            'status' => OrderStatus::Paid,
            'idempotency_key' => $idempotencyKey,
            'request_hash' => hash('sha256', $idempotencyKey.'|'.$amountMinor),
            'paid_at' => $paidAt,
        ]);

        OrderItem::query()->create([
            'order_id' => $order->getKey(),
            'product_type' => ProductType::Course,
            'product_id' => $course->getKey(),
            'learner_id' => $payerId,
            'unit_price_minor' => $amountMinor,
            'currency' => $context->defaultCurrency,
            'discount_minor' => 0,
            'allocated_minor' => $amountMinor,
            'title_ar' => $course->getTranslation('title', 'ar', false) ?: null,
            'title_en' => $course->getTranslation('title', 'en', false) ?: null,
        ]);

        return RowImportResult::imported(Order::class, (int) $order->getKey(), $metadata);
    }

    protected function existingTargetIds(array $targetIds): array
    {
        if ($targetIds === []) {
            return [];
        }

        return Order::query()->whereIn('id', $targetIds)->pluck('id')->map(static fn (mixed $id): int => (int) $id)->values()->all();
    }

    protected function compareSample(array $sourceRow, int $targetId): array
    {
        $order = Order::query()->find($targetId);
        if ($order === null) {
            return ['target_missing'];
        }

        $expectedMinor = (int) round((float) ($sourceRow['amount'] ?? 0) * 100);
        if ($order->total_minor !== $expectedMinor) {
            return ["total_minor expected {$expectedMinor}, got {$order->total_minor}"];
        }

        return [];
    }
}
