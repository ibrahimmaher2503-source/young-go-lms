<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-P8-08: subscription plans (monthly or annual, a price per market, the included courses), a subscription per
 * learner and plan, one period per paid order line, and the renewal reminders already sent. Money is integer minor
 * units. A period is financial history (RESTRICT on its order line); its UNIQUE order line is the idempotency guard for
 * a replayed webhook or approval. A reminder is unique per period and offset.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->string('term', 16);
            $table->unsignedSmallInteger('grace_days')->default(3);
            $table->boolean('is_active')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('is_active');
        });

        Schema::create('subscription_plan_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained('subscription_plans')->cascadeOnDelete();
            $table->string('market', 2);
            $table->string('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->unique(['plan_id', 'market']);
        });

        Schema::create('subscription_plan_courses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained('subscription_plans')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->unique(['plan_id', 'course_id']);
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained('subscription_plans')->restrictOnDelete();
            $table->foreignId('learner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('payer_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 16)->default('active');
            $table->timestamp('started_at');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();
            $table->unique(['plan_id', 'learner_id']);
            $table->index(['status', 'cancelled_at']);
        });

        Schema::create('subscription_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('sequence');
            $table->foreignId('order_item_id')->constrained('order_items')->restrictOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamp('grace_ends_at');
            $table->timestamps();
            $table->unique('order_item_id');
            $table->unique(['subscription_id', 'sequence']);
            $table->index('ends_at');
        });

        Schema::create('subscription_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('period_id')->constrained('subscription_periods')->cascadeOnDelete();
            $table->unsignedSmallInteger('offset_days');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['period_id', 'offset_days']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_reminders');
        Schema::dropIfExists('subscription_periods');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('subscription_plan_courses');
        Schema::dropIfExists('subscription_plan_prices');
        Schema::dropIfExists('subscription_plans');
    }
};
