<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cohorts', function (Blueprint $table): void {
            $table->json('name_translations')->nullable();
            $table->date('enrollment_opens_on')->nullable();
            $table->date('enrollment_closes_on')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cohorts', function (Blueprint $table): void {
            $table->dropColumn(['name_translations', 'enrollment_opens_on', 'enrollment_closes_on']);
        });
    }
};
