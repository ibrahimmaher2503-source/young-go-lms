<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI spec section 12 and 16 (T-P7-04): human help requests raised from an assistant answer. One request per AI message
 * (unique). The question and the answer shown are copied onto the request, so the request stays readable if AI history is
 * pruned later; the AI rows themselves are only referenced.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->foreignId('lesson_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_instructor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('conversation_id')->constrained('ai_conversations')->restrictOnDelete();
            $table->foreignId('ai_message_id')->unique()->constrained('ai_messages')->restrictOnDelete();
            $table->string('status', 24)->default('new');
            $table->string('locale', 8);
            $table->unsignedInteger('video_position')->nullable();
            $table->unsignedInteger('document_version');
            $table->string('ai_status', 24)->nullable();
            $table->json('source_pages')->nullable();
            $table->text('question');
            $table->text('ai_answer');
            $table->timestamp('due_at')->nullable();      // SLA clock: set while the next move is the instructor's
            $table->timestamp('overdue_at')->nullable();  // set once per overdue cycle by the escalation command
            $table->timestamp('first_replied_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['assigned_instructor_id', 'status']);
            $table->index(['student_id', 'lesson_id', 'status']);
            $table->index(['status', 'due_at']);
        });

        Schema::create('help_request_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('help_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->string('role', 12); // instructor, student
            $table->text('body');
            $table->timestamp('created_at')->nullable();
            $table->index('help_request_id');
        });

        Schema::create('help_request_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('help_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('event', 24); // created, replied, student_replied, status_changed, overdue
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index('help_request_id');
        });

        // The feedback endpoint is idempotent per answer and kind.
        Schema::table('ai_feedback', function (Blueprint $table): void {
            $table->unique(['message_id', 'type'], 'ai_feedback_message_type');
        });
    }

    public function down(): void
    {
        Schema::table('ai_feedback', function (Blueprint $table): void {
            $table->dropUnique('ai_feedback_message_type');
        });
        foreach (['help_request_events', 'help_request_messages', 'help_requests'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
