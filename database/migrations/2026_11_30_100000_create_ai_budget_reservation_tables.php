<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Concurrency-safe monthly AI budget ledgers and short-lived in-flight reservations (T-P7-02).
 * Zero PII; holds only scope/period keys, minor-unit reservation amounts, and expiration timestamps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_budget_ledgers', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 40);
            $table->string('period', 7); // Y-m
            $table->timestamps();
            $table->unique(['scope', 'period']);
        });

        Schema::create('ai_budget_reservations', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 40);
            $table->string('period', 7); // Y-m
            $table->unsignedBigInteger('amount_minor');
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->index(['scope', 'period', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_budget_reservations');
        Schema::dropIfExists('ai_budget_ledgers');
    }
};
