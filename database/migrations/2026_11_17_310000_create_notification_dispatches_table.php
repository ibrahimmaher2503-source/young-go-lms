<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** One row per (dedupe key, recipient) that was sent. The unique key is what makes a retried job or a second scheduler run send nothing twice. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_dispatches', function (Blueprint $table): void {
            $table->id();
            $table->string('dedupe_key', 190);
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->string('notification', 120);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['dedupe_key', 'recipient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_dispatches');
    }
};
