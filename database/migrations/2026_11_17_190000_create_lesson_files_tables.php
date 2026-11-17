<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_resources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->restrictOnDelete();
            $table->string('type', 8); // file, image, link
            $table->json('title');
            $table->string('path')->nullable();
            $table->text('url')->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->char('sha256', 64)->nullable();
            $table->boolean('downloadable')->default(true);
            $table->string('visibility', 16)->default('enrolled'); // enrolled, public_preview
            $table->boolean('is_preview')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['lesson_id', 'sort']);
        });

        // One row per uploaded version of a lesson's curriculum PDF (the AI source, AI spec 6.1 and 6.7).
        Schema::create('lesson_curriculum_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('path');
            $table->char('sha256', 64);
            $table->unsignedBigInteger('size');
            $table->unsignedSmallInteger('pages');
            $table->string('status', 24)->default('uploaded');
            $table->text('failure_reason')->nullable();
            $table->decimal('quality_score', 5, 2)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->boolean('active')->default(false);
            $table->timestamps();
            $table->unique(['lesson_id', 'version']);
            $table->unique(['lesson_id', 'sha256']);
            $table->index(['lesson_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_curriculum_documents');
        Schema::dropIfExists('lesson_resources');
    }
};
