<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy `felicitacion_usuario` → `compliment_user`.
 * Pivot: users receiving the compliment + display score (puntuacion).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliment_user', function (Blueprint $table): void {
            $table->foreignId('compliment_id')->constrained('compliments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('score')->nullable();
            $table->timestamps();

            $table->primary(['compliment_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliment_user');
    }
};
