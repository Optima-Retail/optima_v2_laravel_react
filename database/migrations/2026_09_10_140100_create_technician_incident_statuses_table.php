<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Technician incident statuses from legacy EstadosTecnicoIncidenciaEnum.
 * Kept: name, color, lifecycle (ciclo_vida), is_open (abierto).
 * Skipped: modelo_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_incident_statuses', function (Blueprint $table): void {
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
        Schema::dropIfExists('technician_incident_statuses');
    }
};
