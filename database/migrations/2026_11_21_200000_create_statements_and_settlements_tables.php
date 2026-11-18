<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-P8-10: frozen monthly instructor statements and settlement batches. A statement is a snapshot of the ledger for one
 * instructor, currency and closed month (one per trio, UNIQUE). A settlement batch groups ledger entries per instructor; the
 * links are history (RESTRICT, never deleted) and the entries themselves only change `status`. Settlement is a RECORD of an
 * offline payout (method, reference, date): no money moves from the system and no payout details are stored here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructor_statements', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 48)->unique();
            $table->foreignId('instructor_id')->constrained('users')->restrictOnDelete();
            $table->string('currency', 3);
            $table->string('period', 7);
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('entry_count');
            $table->bigInteger('earnings_minor');
            $table->bigInteger('reversals_minor');
            $table->bigInteger('adjustments_minor');
            $table->bigInteger('net_minor');
            $table->bigInteger('settled_minor');
            $table->bigInteger('balance_forward_minor');
            $table->bigInteger('closing_balance_minor');
            $table->json('snapshot');
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at');
            $table->timestamps();
            $table->unique(['instructor_id', 'currency', 'period']);
            $table->index('period');
        });

        Schema::create('instructor_settlements', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 24)->unique();
            $table->string('request_key', 64)->nullable()->unique();
            $table->string('currency', 3);
            $table->string('status', 16)->default('draft');
            $table->string('note', 255)->nullable();
            $table->unsignedInteger('instructor_count');
            $table->unsignedInteger('entry_count');
            $table->bigInteger('total_minor');
            $table->json('carried_forward')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('instructor_settlement_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('settlement_id')->constrained('instructor_settlements')->restrictOnDelete();
            $table->foreignId('instructor_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('entry_count');
            $table->bigInteger('amount_minor');
            $table->string('payout_method', 16)->nullable();
            $table->string('payout_reference', 64)->nullable();
            $table->date('paid_on')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['settlement_id', 'instructor_id']);
            $table->index('instructor_id');
        });

        Schema::create('instructor_settlement_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_id')->constrained('instructor_settlement_items')->restrictOnDelete();
            $table->foreignId('ledger_entry_id')->constrained('instructor_ledger_entries')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['item_id', 'ledger_entry_id']);
            $table->index('ledger_entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructor_settlement_entries');
        Schema::dropIfExists('instructor_settlement_items');
        Schema::dropIfExists('instructor_settlements');
        Schema::dropIfExists('instructor_statements');
    }
};
