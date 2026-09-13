<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Technician incident types from legacy `tecnicos_incidencias_tipos`.
 * Kept: name, due_days (dias_limite), send_mail_to_technician (enviar_mail_tecnico).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_incident_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->integer('due_days');
            $table->boolean('send_mail_to_technician')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_incident_types');
    }
};
