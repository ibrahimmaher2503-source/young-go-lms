<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** One row per access grant, whatever its source (Arch 5.2). Only the Entitlements module reads or writes it (I01). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entitlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('learner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->string('source_type', 24);
            $table->string('source_ref', 64)->default(''); // empty for sources with no external reference
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('status', 16)->default('active');
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->string('reason', 500)->nullable();
            $table->timestamps();
            $table->unique(['source_type', 'source_ref', 'learner_id', 'course_id'], 'entitlements_source_unique');
            $table->index(['learner_id', 'course_id', 'status']);
            $table->index(['status', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entitlements');
    }
};
