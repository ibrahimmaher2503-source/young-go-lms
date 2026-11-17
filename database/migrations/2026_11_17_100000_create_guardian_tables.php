<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardian_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('guardian_child_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guardian_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('child_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 16);
            $table->string('method', 24);
            $table->timestamp('verified_at')->nullable();
            // Consent placeholders for the minors' AI gate (O-015); stay empty until approved.
            $table->timestamp('ai_consent_granted_at')->nullable();
            $table->string('ai_consent_version')->nullable();
            $table->timestamps();
            $table->unique(['guardian_id', 'child_id']);
            $table->index(['child_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_child_links');
        Schema::dropIfExists('guardian_profiles');
    }
};
