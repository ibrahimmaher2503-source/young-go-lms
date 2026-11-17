<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-P6-06: one live certificate per completion. `active` is 1 while the certificate is valid and NULL once revoked, so the
 * unique key (completion, active) allows exactly one live row and any number of revoked ones (their links keep saying "revoked").
 * The name, titles and date are a snapshot: later profile or course edits never change an issued certificate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_completion_id')->constrained('course_completions')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learner_id')->constrained('users')->cascadeOnDelete();
            $table->string('number', 32)->unique();
            $table->string('token', 64)->unique();
            $table->unsignedTinyInteger('active')->nullable()->default(1);
            $table->timestamp('issued_at');
            $table->string('learner_name', 191);
            $table->string('course_title_ar', 255)->nullable();
            $table->string('course_title_en', 255)->nullable();
            $table->date('completed_on');
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason', 500)->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['course_completion_id', 'active']);
            $table->index(['learner_id', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
