<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cohort_members', function (Blueprint $table): void {
            $table->foreignId('seat_order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cohort_members', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('seat_order_item_id');
        });
    }
};
