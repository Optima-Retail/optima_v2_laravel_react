<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Evaluation statuses from legacy `estados` (modelo Evaluacion / EstadosEvaluacionesEnum).
 * Kept: name, color, lifecycle (ciclo_vida), is_open (abierto) — needed for evaluation workflow / FK logic.
 * Skipped: modelo_id, codigo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_statuses', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('color', 32)->nullable();
            $table->unsignedTinyInteger('lifecycle')->nullable();
            $table->boolean('is_open')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_statuses');
    }
};
