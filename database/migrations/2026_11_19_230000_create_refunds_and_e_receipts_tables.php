<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-P8-06: refunds and e-receipts. A refund is a new row that points at the order; the order totals, payment attempts,
 * payment events and transfer proofs are never rewritten (the original transaction history is preserved). Orders and users
 * are RESTRICTed: both tables are financial evidence. `e_receipts` holds the receipt number and an ETA-ready snapshot
 * (seller, buyer, lines, totals, tax) for sales and refunds (credit notes); real submission stays disabled until O-023.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 56)->unique();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->text('reason');
            $table->string('method', 16);
            $table->string('status', 16)->default('requested');
            $table->string('gateway', 24)->nullable();
            $table->string('provider_transaction_id', 96)->nullable();
            $table->string('provider_refund_id', 96)->nullable();
            $table->string('external_reference', 64)->nullable();
            $table->string('failure_code', 48)->nullable();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();
            $table->unique(['gateway', 'provider_refund_id']);
            $table->index(['order_id', 'status']);
            $table->index('status');
        });

        Schema::create('e_receipts', function (Blueprint $table): void {
            $table->id();
            $table->string('receipt_key', 48)->unique();
            $table->string('number', 64)->unique();
            $table->string('kind', 8);
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('refund_id')->nullable()->constrained('refunds')->restrictOnDelete();
            $table->string('currency', 3);
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->nullable();
            $table->unsignedBigInteger('total_minor');
            $table->string('status', 16)->default('not_submitted');
            $table->string('provider', 24)->nullable();
            $table->string('provider_uuid', 96)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->string('failure_code', 48)->nullable();
            $table->json('snapshot');
            $table->timestamp('issued_at');
            $table->timestamps();
            $table->index(['order_id', 'kind']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('e_receipts');
        Schema::dropIfExists('refunds');
    }
};
