<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-P10-03 pre-work: idempotent legacy migration batches, source-to-target ID map with checksums, and exception log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migration_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('source_system', 64);
            $table->string('entity', 64);
            $table->string('mode', 16); // apply | dry_run
            $table->string('status', 16)->default('running'); // running | completed | failed | dry_run
            $table->foreignId('resumed_from_batch_id')->nullable()->constrained('migration_batches')->nullOnDelete();
            $table->unsignedInteger('chunk_size')->default(100);
            $table->string('last_source_id', 128)->nullable();
            $table->unsignedInteger('source_count')->default(0);
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('unchanged_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('rejected_count')->default(0);
            $table->unsignedInteger('exceptions_count')->default(0);
            $table->json('report')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['entity', 'status']);
            $table->index(['source_system', 'entity', 'created_at']);
        });

        Schema::create('migration_records', function (Blueprint $table): void {
            $table->id();
            $table->string('source_system', 64);
            $table->string('entity', 64);
            $table->string('source_table', 64);
            $table->string('source_id', 128);
            $table->string('target_type', 128)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->char('checksum', 64);
            $table->string('status', 16); // imported | updated | skipped | rejected
            $table->string('rejection_reason', 255)->nullable();
            $table->foreignId('last_batch_id')->nullable()->constrained('migration_batches')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
            $table->unique(['source_system', 'entity', 'source_id'], 'migration_records_source_unique');
            $table->index(['entity', 'status']);
            $table->index(['target_type', 'target_id']);
            $table->index('last_batch_id');
        });

        Schema::create('migration_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->nullable()->constrained('migration_batches')->cascadeOnDelete();
            $table->string('source_system', 64);
            $table->string('entity', 64);
            $table->string('source_table', 64);
            $table->string('source_id', 128);
            $table->string('code', 64);
            $table->string('severity', 24); // warning | error | decision_required
            $table->string('message', 500);
            $table->json('context')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution', 255)->nullable();
            $table->timestamps();
            $table->index(['batch_id', 'entity']);
            $table->index(['entity', 'code']);
            $table->index(['severity', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_exceptions');
        Schema::dropIfExists('migration_records');
        Schema::dropIfExists('migration_batches');
    }
};
