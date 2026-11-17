<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Progress is keyed by learner and curriculum item, never by entitlement (ED-004), and written only by RecordProgress. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('learner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('curriculum_item_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16)->default('in_progress');
            $table->unsignedInteger('position_seconds')->default(0);
            $table->unsignedInteger('furthest_seconds')->default(0);
            $table->unsignedTinyInteger('percent')->default(0);
            $table->string('completion_source', 16)->nullable(); // auto, manual
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamps();
            $table->unique(['learner_id', 'curriculum_item_id']);
            $table->index(['learner_id', 'last_accessed_at']);
        });

        Schema::create('course_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('learner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('percent')->default(0);
            $table->unsignedInteger('completed_required')->default(0);
            $table->unsignedInteger('total_required')->default(0);
            $table->foreignId('last_item_id')->nullable()->constrained('curriculum_items')->nullOnDelete();
            $table->unsignedInteger('last_position_seconds')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['learner_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_progress');
        Schema::dropIfExists('lesson_progress');
    }
};
