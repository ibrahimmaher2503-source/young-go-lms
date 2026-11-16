<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('features', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('scope');
            $table->text('value');
            $table->timestamps();
            $table->unique(['name', 'scope']);
        });

        Schema::create('flag_approvals', function (Blueprint $table): void {
            $table->id();
            $table->string('flag')->index();
            $table->string('approval_ref');
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('at');
        });

        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('group');
            $table->string('name');
            $table->boolean('locked')->default(false);
            $table->json('payload');
            $table->timestamps();
            $table->unique(['group', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('flag_approvals');
        Schema::dropIfExists('features');
    }
};
