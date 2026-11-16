<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('email')->nullable()->change();
            $table->string('username', 64)->nullable()->unique();
            $table->string('phone', 16)->nullable();
            $table->string('name_ar')->nullable();
            $table->string('name_en')->nullable();
            $table->string('display_name')->nullable();
            $table->unsignedSmallInteger('birth_year')->nullable();
            $table->unsignedTinyInteger('birth_month')->nullable();
            $table->string('timezone', 64)->default('UTC');
            $table->string('status', 16)->default('active');
            $table->timestamp('last_login_at')->nullable();
        });

        Schema::create('consents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('guardian_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('type', 32);
            $table->string('version');
            $table->timestamp('granted_at');
            $table->timestamp('withdrawn_at')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('users')->whereNull('email')->exists()) {
            throw new RuntimeException('Cannot roll back nullable email while email-less accounts exist.');
        }

        Schema::dropIfExists('consents');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'phone', 'name_ar', 'name_en', 'display_name', 'birth_year', 'birth_month', 'timezone', 'status', 'last_login_at']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
