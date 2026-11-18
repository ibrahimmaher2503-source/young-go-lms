<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-P8-09: commission rules (a rate per instructor and/or course and sale type, with effective dates) and the append-only
 * instructor ledger. Money is integer minor units; `amount_minor` is signed (reversals and adjustments can be negative).
 * Ledger rows are financial history: every link is RESTRICT, `source_key` is UNIQUE (the idempotency guard for replays), and
 * the model refuses any change except the status column (settlement, T-P8-10). Commission components (gross, discount, base,
 * rate) are stored separately because the commissionable base is OPEN (O-011).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_commission_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instructor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('course_id')->nullable()->constrained('courses')->restrictOnDelete();
            $table->string('sale_type', 16);
            $table->unsignedSmallInteger('rate_bps')->nullable();
            $table->unsignedBigInteger('fixed_amount_minor')->nullable();
            $table->string('currency', 3)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['sale_type', 'effective_from']);
        });

        Schema::create('instructor_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instructor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('course_id')->nullable()->constrained('courses')->restrictOnDelete();
            $table->string('type', 16);
            $table->string('sale_type', 16)->nullable();
            $table->string('status', 16)->default('accrued');
            $table->string('source_key', 96)->unique();
            $table->foreignId('order_id')->nullable()->constrained('orders')->restrictOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->restrictOnDelete();
            $table->foreignId('allocation_id')->nullable()->constrained('order_item_allocations')->restrictOnDelete();
            $table->foreignId('subscription_period_id')->nullable()->constrained('subscription_periods')->restrictOnDelete();
            $table->foreignId('refund_id')->nullable()->constrained('refunds')->restrictOnDelete();
            $table->foreignId('reverses_entry_id')->nullable()->constrained('instructor_ledger_entries')->restrictOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('rule_id')->nullable()->constrained('course_commission_rules')->restrictOnDelete();
            $table->string('rule_source', 16)->nullable();
            $table->string('currency', 3);
            $table->bigInteger('gross_minor')->nullable();
            $table->bigInteger('discount_minor')->nullable();
            $table->bigInteger('base_minor')->nullable();
            $table->unsignedSmallInteger('rate_bps')->nullable();
            $table->unsignedBigInteger('fixed_amount_minor')->nullable();
            $table->bigInteger('amount_minor');
            $table->string('reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('earned_at');
            $table->timestamps();
            $table->index(['instructor_id', 'status']);
            $table->index(['order_id', 'type']);
            $table->index('course_id');
            $table->index('earned_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructor_ledger_entries');
        Schema::dropIfExists('course_commission_rules');
    }
};
