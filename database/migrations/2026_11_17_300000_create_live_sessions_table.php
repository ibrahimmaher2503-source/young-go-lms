<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Live sessions are curriculum items (ED-002): the item row places them in a section, this row holds the schedule. Times are stored in UTC. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->foreignId('cohort_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('instructor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('title');
            $table->json('description')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('timezone', 64); // display timezone chosen by the scheduler; never used for storage
            $table->string('provider', 24)->default('manual_zoom'); // manual_zoom | meet | bbb | custom
            $table->text('meeting_url')->nullable(); // encrypted by the model cast
            $table->unsignedSmallInteger('join_window_minutes')->default(15);
            $table->string('status', 16)->default('scheduled'); // scheduled | open | completed | cancelled
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['course_id', 'status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_sessions');
    }
};
