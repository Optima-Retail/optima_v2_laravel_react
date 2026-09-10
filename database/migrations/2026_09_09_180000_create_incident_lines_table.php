<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy `lineas_incidencia` → `incident_lines` (Acciones timeline).
 *
 * Kept:
 * - incidencia_id → incident_id
 * - fecha_inicio → started_at
 * - fecha_final → ended_at
 * - comentario → comment
 * - estado_id → incident_status_id → incident_statuses
 * - usuario_id → user_id → users
 * - tiempo → duration_minutes (legacy KPI minutes on the line)
 * - soft deletes
 *
 * No dead columns found — all are written/read by StoreIncidenceUseCase / Acciones UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('incident_id')->constrained('incidents')->cascadeOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->text('comment');
            $table->foreignId('incident_status_id')->constrained('incident_statuses')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('duration_minutes')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['incident_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_lines');
    }
};
