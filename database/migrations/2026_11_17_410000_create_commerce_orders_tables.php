<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-P8-01: course prices per market, server-priced orders and their lines. Money is integer minor units plus a currency.
 * `unique(payer_id, idempotency_key)` makes a retried checkout return the same order. Users are RESTRICTed: an order is
 * financial history and must outlive any cleanup of an account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('market', 2);
            $table->string('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->timestamps();
            $table->unique(['course_id', 'market']);
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 24)->unique();
            $table->foreignId('payer_id')->constrained('users')->restrictOnDelete();
            $table->string('market', 2);
            $table->string('currency', 3);
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
            $table->string('status', 24)->default('pending');
            $table->string('idempotency_key', 64);
            $table->string('request_hash', 64);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['payer_id', 'idempotency_key']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('product_type', 16);
            $table->unsignedBigInteger('product_id');
            $table->foreignId('learner_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('unit_price_minor');
            $table->string('currency', 3);
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('allocated_minor')->nullable();
            $table->string('title_ar', 255)->nullable();
            $table->string('title_en', 255)->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'product_type', 'product_id', 'learner_id'], 'order_items_line_unique');
            $table->index(['learner_id', 'product_type', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('course_prices');
    }
};
