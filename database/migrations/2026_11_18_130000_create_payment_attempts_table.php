<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-P8-02: gateway checkout attempts. `unique(order_id, sequence)` numbers the attempts of an order and `reference`
 * (order reference + sequence) is what the gateway echoes back. `active_order_id` is filled only while an attempt is open
 * and is unique, so concurrent checkouts cannot create two sessions for one order. The order is RESTRICTed: an attempt is
 * financial evidence. No merchant secret is stored; `checkout_url` holds an encrypted per-session link.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->string('gateway', 24);
            $table->unsignedInteger('sequence');
            $table->string('reference', 48)->unique();
            $table->string('status', 16)->default('initiated');
            $table->unsignedBigInteger('active_order_id')->nullable()->unique();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('provider_intention_id', 96)->nullable();
            $table->string('provider_order_id', 96)->nullable();
            $table->text('checkout_url')->nullable();
            $table->string('failure_code', 48)->nullable();
            $table->boolean('signature_valid')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->unique(['order_id', 'sequence']);
            $table->unique(['gateway', 'provider_intention_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};
