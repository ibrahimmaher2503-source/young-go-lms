<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attempts keep their own copy of the questions (the snapshot, including the answer key) and of the settings they were
 * taken under, so editing a quiz later never changes a past attempt. The key lives only on the server (T-P6-02 A3, A4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->restrictOnDelete();
            $table->foreignId('learner_id')->constrained('users')->restrictOnDelete();
            $table->unsignedSmallInteger('attempt_no');
            $table->string('status', 16)->default('in_progress'); // in_progress | submitted
            $table->timestamp('started_at');
            $table->timestamp('deadline_at')->nullable(); // set at start from the time limit; the server enforces it
            $table->timestamp('submitted_at')->nullable();
            $table->string('submitted_by', 16)->nullable(); // learner | auto (time ran out or the expiry job)
            $table->json('snapshot'); // questions, options and the key as they were at the start
            $table->json('settings'); // pass mark, visibility, progression, time limit as they were at the start
            $table->decimal('score', 7, 2)->nullable();
            $table->decimal('total', 7, 2)->default(0);
            $table->decimal('percent', 5, 2)->nullable();
            $table->boolean('passed')->nullable(); // null: no pass mark, or answers still waiting for a person
            $table->boolean('needs_review')->default(false); // an essay is waiting for grading (T-P6-04)
            $table->timestamp('released_at')->nullable(); // staff released the result when visibility is hidden
            $table->timestamps();
            $table->unique(['quiz_id', 'learner_id', 'attempt_no']);
            $table->index(['learner_id', 'status']);
            $table->index(['status', 'deadline_at']);
        });

        Schema::create('quiz_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quiz_attempt_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('question_id'); // plain id on purpose: the question may change or be removed later
            $table->unsignedInteger('question_version');
            $table->json('answer')->nullable(); // {selected: [option keys]} or {text: "..."}
            $table->boolean('is_correct')->nullable(); // null while an essay waits for a person
            $table->decimal('points_awarded', 6, 2)->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();
            $table->unique(['quiz_attempt_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_answers');
        Schema::dropIfExists('quiz_attempts');
    }
};
