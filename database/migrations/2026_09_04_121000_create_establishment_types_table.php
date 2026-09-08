<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('establishment_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code', 64);
            $table->unsignedInteger('health_and_safety_delay_days')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('establishment_types');
    }
};
