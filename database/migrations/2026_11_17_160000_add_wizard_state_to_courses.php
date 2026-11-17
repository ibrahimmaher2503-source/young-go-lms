<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            // Save/resume state for the course wizard and the Information step content.
            $table->unsignedTinyInteger('wizard_step')->default(1);
            $table->json('wizard_completed')->nullable();
            $table->json('faqs')->nullable();
            $table->json('requirements')->nullable();
            $table->json('outcomes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropColumn(['wizard_step', 'wizard_completed', 'faqs', 'requirements', 'outcomes']);
        });
    }
};
