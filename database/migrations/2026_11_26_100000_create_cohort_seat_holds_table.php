<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A cohort seat held for an unpaid order line: counts against capacity until it expires, is released or becomes a seat on payment. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cohort_seat_holds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cohort_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('order_item_id')->unique()->constrained('order_items')->cascadeOnDelete();
            $table->string('status', 16); // held | converted | released
            $table->timestamp('expires_at');
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->string('release_reason', 32)->nullable(); // expired | cancelled | failed | full_at_payment
            $table->timestamps();
            $table->index(['cohort_id', 'status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cohort_seat_holds');
    }
};
