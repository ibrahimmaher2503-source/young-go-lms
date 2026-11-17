<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardian_link_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('learner_id')->constrained('users')->restrictOnDelete();
            $table->string('code_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->foreignId('redeemed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['learner_id', 'used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_link_codes');
    }
};
