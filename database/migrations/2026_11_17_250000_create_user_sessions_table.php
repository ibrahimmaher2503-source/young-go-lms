<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('last_seen_at');
            $table->timestamp('revoked_at')->nullable();
            $table->index(['user_id', 'revoked_at', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_sessions');
    }
};
