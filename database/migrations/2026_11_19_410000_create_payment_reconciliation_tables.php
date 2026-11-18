<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-P8-11, YG-GAP-PAY-003: one row per gateway and reconciled day, and the mismatches that run found. Reconciliation only
 * reports: nothing here changes an order, attempt or entitlement. `unique(gateway, mismatch_key)` keeps a re-run of the same
 * day (or the next day seeing the same transaction) from duplicating a finding or reopening one finance already reviewed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->string('gateway', 24);
            $table->date('run_date');
            $table->timestamp('window_start');
            $table->timestamp('window_end');
            $table->unsignedInteger('transactions_seen')->default(0);
            $table->unsignedInteger('local_attempts')->default(0);
            $table->unsignedInteger('matched')->default(0);
            $table->unsignedInteger('mismatched')->default(0);
            $table->timestamp('completed_at');
            $table->timestamps();
            $table->unique(['gateway', 'run_date']);
        });

        Schema::create('payment_reconciliation_mismatches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_reconciliation_id')->constrained('payment_reconciliations')->restrictOnDelete();
            $table->string('gateway', 24);
            $table->string('type', 32);
            $table->string('mismatch_key', 140);
            $table->string('provider_transaction_id', 96)->nullable();
            $table->string('attempt_reference', 48)->nullable();
            $table->foreignId('payment_attempt_id')->nullable()->constrained('payment_attempts')->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->restrictOnDelete();
            $table->unsignedBigInteger('gateway_amount_minor')->nullable();
            $table->unsignedBigInteger('local_amount_minor')->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('reason', 48);
            $table->string('status', 16)->default('open');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['gateway', 'mismatch_key']);
            $table->index(['status', 'created_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reconciliation_mismatches');
        Schema::dropIfExists('payment_reconciliations');
    }
};
