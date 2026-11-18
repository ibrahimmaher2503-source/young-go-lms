<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-P8-07: bundles (a priced set of courses), their member courses with an optional allocation weight, one price per
 * market, and the per-course allocation stored for every bundle order line. Money is integer minor units. The
 * allocation rows are financial history for the ledger and refunds (T-P8-06, T-P8-09): they RESTRICT their order line
 * and course, and `skipped_existing` records a course the learner already had when the bundle was granted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bundles', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->boolean('is_active')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('is_active');
        });

        Schema::create('bundle_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bundle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('allocation_weight')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->unique(['bundle_id', 'course_id']);
        });

        Schema::create('bundle_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bundle_id')->constrained()->cascadeOnDelete();
            $table->string('market', 2);
            $table->string('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->unique(['bundle_id', 'market']);
        });

        Schema::create('order_item_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_item_id')->constrained('order_items')->restrictOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('weight');
            $table->unsignedBigInteger('allocated_minor');
            $table->foreignId('entitlement_id')->nullable()->constrained('entitlements')->nullOnDelete();
            $table->boolean('skipped_existing')->default(false);
            $table->timestamp('granted_at')->nullable();
            $table->timestamps();
            $table->unique(['order_item_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_allocations');
        Schema::dropIfExists('bundle_prices');
        Schema::dropIfExists('bundle_items');
        Schema::dropIfExists('bundles');
    }
};
