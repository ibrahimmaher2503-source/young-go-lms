<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('age_bands', function (Blueprint $table): void {
            $table->id();
            $table->json('label');
            $table->unsignedSmallInteger('min');
            $table->unsignedSmallInteger('max')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['sort', 'min', 'max']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('age_bands');
    }
};
