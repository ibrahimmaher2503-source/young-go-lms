<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Supports the audit viewer: scoped to log_name, sorted by created_at, filtered by event or actor.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table): void {
            $table->index(['log_name', 'created_at'], 'activity_log_log_created_index');
            $table->index(['log_name', 'event', 'created_at'], 'activity_log_log_event_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table): void {
            $table->dropIndex('activity_log_log_event_created_index');
            $table->dropIndex('activity_log_log_created_index');
        });
    }
};
