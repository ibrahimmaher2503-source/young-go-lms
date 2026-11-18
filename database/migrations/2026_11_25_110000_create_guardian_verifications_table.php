<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** O-015: one row per guardian (parent account). verified_at is the flag the minors' gate and the legal review read. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardian_verifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('code_hash', 64)->nullable();
            $table->timestamp('code_expires_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('code_sent_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_verifications');
    }
};
