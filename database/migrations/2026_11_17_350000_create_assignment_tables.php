<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assignment configuration, the instructor brief files, and learner submissions with their revisions. A submission keeps
 * every revision (text and files) so what was handed in stays as it was; files live on the private disk (T-P6-03 A2).
 * Late policy values and revision counting are working choices pending the owner (O-024).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table): void {
            $table->json('instructions')->nullable(); // translatable text shown to learners
            $table->timestamp('due_at')->nullable(); // UTC; null means no due date
            $table->json('allowed_types')->nullable(); // subset of text, image, video, audio, document
            $table->unsignedInteger('max_size_kb')->nullable(); // per file; null uses config('assignments.max_kilobytes')
            $table->string('late_policy', 16)->default('not_allowed'); // not_allowed | allowed | penalty
            $table->unsignedTinyInteger('late_penalty_percent')->nullable(); // used when late_policy is penalty; applied at grading
            $table->unsignedTinyInteger('max_revisions')->default(0); // resubmissions allowed after the first submission
        });

        Schema::create('assignment_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->json('title');
            $table->string('path');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->string('sha256', 64);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('assignment_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->restrictOnDelete();
            $table->foreignId('learner_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 16)->default('draft'); // draft | submitted | returned | resubmitted | graded
            $table->unsignedSmallInteger('current_revision')->default(1);
            $table->timestamps();
            $table->unique(['assignment_id', 'learner_id']);
            $table->index(['assignment_id', 'status']);
        });

        Schema::create('submission_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('submission_id')->constrained('assignment_submissions')->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->longText('text_body')->nullable();
            $table->timestamp('submitted_at')->nullable(); // null while it is still a draft
            $table->boolean('is_late')->default(false);
            $table->unsignedTinyInteger('late_penalty_percent')->nullable(); // copied from the assignment at submit time
            $table->timestamps();
            $table->unique(['submission_id', 'number']);
        });

        Schema::create('submission_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('revision_id')->constrained('submission_revisions')->cascadeOnDelete();
            $table->string('kind', 12); // image | video | audio | document
            $table->string('display_name', 150);
            $table->string('path');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->string('sha256', 64);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_files');
        Schema::dropIfExists('submission_revisions');
        Schema::dropIfExists('assignment_submissions');
        Schema::dropIfExists('assignment_attachments');
        Schema::table('assignments', function (Blueprint $table): void {
            $table->dropColumn(['instructions', 'due_at', 'allowed_types', 'max_size_kb', 'late_policy', 'late_penalty_percent', 'max_revisions']);
        });
    }
};
