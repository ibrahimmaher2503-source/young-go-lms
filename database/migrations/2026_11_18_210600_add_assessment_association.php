<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('curriculum_items', function (Blueprint $table): void {
            $table->string('association', 16)->nullable();
            $table->foreignId('associated_lesson_item_id')->nullable()->constrained('curriculum_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('curriculum_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('associated_lesson_item_id');
            $table->dropColumn('association');
        });
    }
};
