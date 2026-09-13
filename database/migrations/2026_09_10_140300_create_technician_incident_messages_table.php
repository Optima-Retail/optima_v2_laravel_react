<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Technician incident messages from legacy `tecnicos_incidencias_mensajes`.
 * Kept: body, type (legacy tipo: text/image). No softDeletes (legacy had none).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('technician_incident_messages')) {
            return;
        }

        Schema::create('technician_incident_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('technician_incident_id')->constrained('technician_incidents')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->string('type')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->timestamps();

            $table->index(['technician_incident_id', 'created_at'], 'ti_messages_incident_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_incident_messages');
    }
};
