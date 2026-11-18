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
            $table->string('overview_video_provider', 32)->nullable();
            $table->string('overview_video_url', 2048)->nullable();
            $table->string('preview_video_provider', 32)->nullable();
            $table->string('preview_video_url', 2048)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropColumn(['overview_video_provider', 'overview_video_url', 'preview_video_provider', 'preview_video_url']);
        });
    }
};
