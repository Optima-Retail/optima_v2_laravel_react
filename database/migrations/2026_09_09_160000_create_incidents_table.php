<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy incidencias → incidents.
 *
 * Kept:
 * - asunto → subject
 * - comentario → comment
 * - estado_id → incident_status_id → incident_statuses
 * - prioridad_id → incident_priority_id → incident_priorities
 * - tipo_id → incident_type_id → incident_types
 * - subtipo_id → incident_subtype_id → incident_subtypes
 * - fecha_control → control_at
 * - fecha_cierre → closed_at
 * - tiempo → duration_seconds
 * - tiempo_qc → qc_duration_seconds
 * - solicitante_id → requester_user_id → users
 * - responsable_id → responsible_user_id → users
 * - responsable_qc_id → qc_responsible_user_id → users
 * - origen_modelo_id + origen_relacion_id → origin_type (string nullable) + origin_id (unsignedBigInteger nullable)
 * - relacion_modelo_id + relacion_relacion_id → related_type (string nullable) + related_id (unsignedBigInteger nullable)
 *
 * V2 scoping helper (not in legacy after merge; evaluations pattern):
 * - establishment_id nullable → establishments (company-scoped lists)
 * - evaluation_id nullable → evaluations (when related is evaluation)
 *
 * Skipped: fecha_limite, fecha_recibida, fecha_ultima_revision (dead/broken in prod).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table): void {
            $table->id();
            $table->string('subject');
            $table->text('comment')->nullable();
            $table->foreignId('incident_status_id')->nullable()->constrained('incident_statuses')->nullOnDelete();
            $table->foreignId('incident_priority_id')->nullable()->constrained('incident_priorities')->nullOnDelete();
            $table->foreignId('incident_type_id')->nullable()->constrained('incident_types')->nullOnDelete();
            $table->foreignId('incident_subtype_id')->nullable()->constrained('incident_subtypes')->nullOnDelete();
            $table->foreignId('establishment_id')->nullable()->constrained('establishments')->nullOnDelete();
            $table->foreignId('evaluation_id')->nullable()->constrained('evaluations')->nullOnDelete();
            $table->dateTime('control_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->integer('qc_duration_seconds')->nullable();
            $table->foreignId('requester_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('qc_responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('origin_type')->nullable();
            $table->unsignedBigInteger('origin_id')->nullable();
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['establishment_id', 'incident_status_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
