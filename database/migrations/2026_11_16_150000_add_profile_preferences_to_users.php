<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('avatar_path')->nullable();
            $table->json('notification_preferences')->nullable();
            $table->string('certificate_name_ar')->nullable();
            $table->string('certificate_name_en')->nullable();
            $table->timestamp('certificate_names_locked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['avatar_path', 'notification_preferences', 'certificate_name_ar', 'certificate_name_en', 'certificate_names_locked_at']);
        });
    }
};
