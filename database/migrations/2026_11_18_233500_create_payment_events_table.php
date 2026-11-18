<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-P8-03: one row per verified gateway callback. `unique(gateway, event_key)` is the idempotency guard: `event_key` is
 * the provider transaction id plus its state, so a replay is a no-op while a later state of the same transaction (pending,
 * then success) is still processed. Rows exist only for callbacks whose signature was proven; the payload is the sanitised
 * provider data (no card data, no secrets). The order and attempt are RESTRICTed: an event is financial evidence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_events', function (Blueprint $table): void {
            $table->id();
            $table->string('gateway', 24);
            $table->string('event_key', 120);
            $table->string('provider_event_id', 96);
            $table->string('state', 16);
            $table->foreignId('payment_attempt_id')->nullable()->constrained('payment_attempts')->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->restrictOnDelete();
            $table->string('attempt_reference', 48)->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('outcome', 16);
            $table->string('reason', 48)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('processed_at');
            $table->timestamps();
            $table->unique(['gateway', 'event_key']);
            $table->index(['gateway', 'provider_event_id']);
            $table->index('outcome');
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
    }
};
