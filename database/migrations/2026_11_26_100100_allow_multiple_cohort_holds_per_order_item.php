<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cohort_seat_holds', function (Blueprint $table): void {
            $table->unique(['order_item_id', 'cohort_id'], 'cohort_holds_order_item_cohort_unique');
        });
        Schema::table('cohort_seat_holds', function (Blueprint $table): void {
            $table->dropUnique(['order_item_id']);
        });
    }

    public function down(): void
    {
        // The old schema can represent only one hold per order line.
        Schema::table('cohort_seat_holds', function (Blueprint $table): void {
            $table->unique('order_item_id');
        });
        Schema::table('cohort_seat_holds', function (Blueprint $table): void {
            $table->dropUnique('cohort_holds_order_item_cohort_unique');
        });
    }
};
