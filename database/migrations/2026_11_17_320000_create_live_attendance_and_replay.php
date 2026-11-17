<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** One attendance row per learner per live session, and the link from a session to its replay lesson item. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_attendance', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('live_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learner_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 16); // present | late | absent | excused
            $table->string('source', 16); // join (recorded when the learner used the join link) | manual
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('marked_at');
            $table->string('note', 500)->nullable();
            $table->timestamps();
            $table->unique(['live_session_id', 'learner_id']);
        });

        Schema::table('live_sessions', function (Blueprint $table): void {
            $table->foreignId('replay_item_id')->nullable()->constrained('curriculum_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('live_sessions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('replay_item_id');
        });
        Schema::dropIfExists('live_attendance');
    }
};
