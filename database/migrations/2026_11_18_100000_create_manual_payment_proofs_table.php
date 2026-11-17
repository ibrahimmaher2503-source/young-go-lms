<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-P8-04: Instapay/manual transfer proofs. `reference_key` (normalised transfer reference) is unique and is only held
 * while the proof is pending or approved, so a duplicate reference can never be approved twice yet a rejected proof's
 * reference can be corrected and resubmitted. `active_order_id` allows one pending proof per order. Orders and users are
 * RESTRICTed: a proof is financial evidence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_payment_proofs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('payer_id')->constrained('users')->restrictOnDelete();
            $table->string('payer_name', 120);
            $table->string('sender_account', 64);
            $table->string('transfer_reference', 64);
            $table->string('reference_key', 64)->nullable()->unique();
            $table->unsignedBigInteger('active_order_id')->nullable()->unique();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->timestamp('transferred_at');
            $table->string('file_path', 255);
            $table->string('file_mime', 100);
            $table->unsignedInteger('file_size');
            $table->string('file_sha256', 64);
            $table->string('status', 16)->default('pending');
            $table->timestamp('sla_due_at');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_payment_proofs');
    }
};
