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
        Schema::create('certificate_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('locale', 2)->unique();
            $table->string('layout', 32);
            $table->text('issuer_text')->nullable();
            $table->timestamps();
        });

        DB::table('certificate_templates')->insert([
            ['locale' => 'ar', 'layout' => 'standard'],
            ['locale' => 'en', 'layout' => 'standard'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_templates');
    }
};
