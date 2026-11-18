<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table): void {
            $table->string('learner_name_ar')->nullable();
            $table->string('learner_name_en')->nullable();
        });

        DB::table('users')->whereNull('certificate_names_locked_at')
            ->whereIn('id', DB::table('certificates')->select('learner_id'))
            ->update(['certificate_names_locked_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table): void {
            $table->dropColumn(['learner_name_ar', 'learner_name_en']);
        });
    }
};
