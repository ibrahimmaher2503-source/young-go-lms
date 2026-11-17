<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** AI spec section 16 data model (help requests come with T-P7-04). The AI stays off: nothing here enables it. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_document_pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained('lesson_curriculum_documents')->restrictOnDelete();
            $table->unsignedSmallInteger('page_no');
            $table->longText('raw_text')->nullable();
            $table->longText('approved_text')->nullable();
            $table->boolean('ocr_used')->default(false);
            $table->decimal('quality_score', 5, 2)->nullable();
            $table->timestamps();
            $table->unique(['document_id', 'page_no']);
        });

        Schema::create('lesson_document_chunks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained('lesson_curriculum_documents')->restrictOnDelete();
            $table->unsignedSmallInteger('page_from');
            $table->unsignedSmallInteger('page_to');
            $table->text('text');
            $table->longText('embedding')->nullable(); // chunked mode only
            $table->timestamps();
            $table->index(['document_id', 'page_from']);
        });

        Schema::create('lesson_key_terms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained('lesson_curriculum_documents')->restrictOnDelete();
            $table->string('term');
            $table->string('locale', 8);
            $table->boolean('approved')->default(false);
            $table->timestamps();
            $table->index(['document_id', 'locale']);
        });

        Schema::create('ai_prompt_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('version')->unique();
            $table->longText('template');
            $table->json('style_tiers'); // kids, teens, adults instruction snippets
            $table->boolean('active')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('ai_conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->foreignId('lesson_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('document_version');
            $table->string('locale', 8);
            $table->string('style_tier', 8);
            $table->unsignedInteger('video_position')->nullable();
            $table->string('status', 16)->default('open');
            $table->timestamps();
            $table->index(['student_id', 'lesson_id']);
        });

        Schema::create('ai_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained('ai_conversations')->restrictOnDelete();
            $table->string('role', 12);
            $table->text('content');
            $table->string('status', 24)->nullable();
            $table->json('source_pages')->nullable();
            $table->boolean('quote_verified')->nullable();
            $table->json('guard_flags')->nullable();
            $table->unsignedInteger('prompt_version')->nullable();
            $table->string('model', 80)->nullable();
            $table->boolean('cache_hit')->default(false);
            $table->timestamp('created_at')->nullable();
            $table->index('conversation_id');
        });

        Schema::create('ai_feedback', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('message_id')->constrained('ai_messages')->restrictOnDelete();
            $table->string('type', 16); // understood, simpler, not_understood
            $table->timestamps();
        });

        // Usage rows carry only an opaque hash of the student, never the student id (data minimisation).
        Schema::create('ai_usage', function (Blueprint $table): void {
            $table->id();
            $table->char('student_hash', 64);
            $table->foreignId('lesson_id')->constrained()->restrictOnDelete();
            $table->string('provider', 40);
            $table->string('model', 80)->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('cached_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedBigInteger('cost_minor')->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->string('result', 24);
            $table->timestamp('created_at')->nullable();
            $table->index(['lesson_id', 'created_at']);
        });

        Schema::create('ai_answer_cache', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('lesson_id');
            $table->unsignedInteger('document_version');
            $table->char('question_hash', 64);
            $table->string('locale', 8);
            $table->string('style_tier', 8);
            $table->json('answer_json');
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['lesson_id', 'document_version', 'question_hash', 'locale', 'style_tier'], 'ai_answer_cache_key');
        });

        Schema::create('ai_quota_counters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->string('scope', 40); // student_day, student_lesson_day:<lesson id>
            $table->string('period', 16); // Y-m-d
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();
            $table->unique(['student_id', 'scope', 'period']);
        });

        Schema::create('ai_eval_cases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->restrictOnDelete();
            $table->text('question');
            $table->string('expected_status', 24);
            $table->json('expected_pages')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_eval_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 40);
            $table->unsignedInteger('prompt_version')->nullable();
            $table->json('metrics')->nullable();
            $table->string('result', 16)->nullable();
            $table->timestamps();
        });

        Schema::create('ai_safeguarding_flags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('ai_conversations')->restrictOnDelete();
            $table->string('category', 40);
            $table->string('status', 16)->default('open');
            $table->foreignId('handled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['ai_safeguarding_flags', 'ai_eval_runs', 'ai_eval_cases', 'ai_quota_counters', 'ai_answer_cache', 'ai_usage', 'ai_feedback', 'ai_messages', 'ai_conversations', 'ai_prompt_versions', 'lesson_key_terms', 'lesson_document_chunks', 'lesson_document_pages'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
