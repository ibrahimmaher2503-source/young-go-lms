<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lesson_id')->unique()->constrained()->restrictOnDelete();
            $table->string('provider', 24);
            $table->string('kind', 16);
            $table->string('external_id')->nullable();
            $table->text('url')->nullable();
            $table->string('path')->nullable();
            $table->string('protection_tier', 2);
            $table->text('exception_reason')->nullable();
            $table->foreignId('exception_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('exception_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('status', 16)->default('ready');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['provider', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
