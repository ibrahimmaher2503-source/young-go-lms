<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** T-P6-05: per-course completion rules (O-020) and the one completion row per learner and course. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_completion_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('lesson_percent')->nullable(); // null = the rule is off
            $table->boolean('require_quizzes')->default(false);
            $table->boolean('require_assignments')->default(false);
            $table->unsignedTinyInteger('attendance_percent')->nullable(); // null = the rule is off
            $table->boolean('manual_approval')->default(false);
            $table->timestamps();
        });

        Schema::create('course_completions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learner_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('completed_at');
            $table->string('source', 8); // auto, manual
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 500)->nullable();
            $table->json('snapshot')->nullable(); // which criteria were met when it was recorded
            $table->timestamps();
            $table->unique(['course_id', 'learner_id']);
            $table->index(['learner_id', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_completions');
        Schema::dropIfExists('course_completion_rules');
    }
};
