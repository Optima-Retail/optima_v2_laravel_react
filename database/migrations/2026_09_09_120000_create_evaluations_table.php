<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Evaluations from legacy `evaluaciones`.
 *
 * Kept (renamed):
 * - asunto → subject
 * - id_publico → public_id
 * - establecimiento_id → establishment_id
 * - estado_id → evaluation_status_id
 * - responsable_id → responsible_user_id
 * - fecha_proxima_accion → next_action_at
 * - fecha_cierre → closed_at
 * - pregunta_facility → facility_question
 * - pregunta_tecnico → technician_question
 * - visitas → visit_count
 * - tiempo_qc → qc_duration_minutes
 * - num_llamadas → call_count
 * - primer_intento_contacto → first_contact_attempt_at
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table): void {
            $table->id();
            $table->string('subject')->nullable();
            $table->uuid('public_id')->unique();
            $table->foreignId('establishment_id')->constrained('establishments')->cascadeOnDelete();
            $table->foreignId('evaluation_status_id')->nullable()->constrained('evaluation_statuses')->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('next_action_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->string('facility_question')->nullable();
            $table->string('technician_question')->nullable();
            $table->unsignedInteger('visit_count')->default(0);
            $table->integer('qc_duration_minutes')->nullable();
            $table->unsignedInteger('call_count')->default(0);
            $table->dateTime('first_contact_attempt_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['establishment_id', 'evaluation_status_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
