<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy tecnico_tipos_confirmacion_asistencia → technician_attendance_confirmation_types.
 *
 * Kept: nombre → name, timestamps.
 * Soft deletes added for config-catalog consistency.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_attendance_confirmation_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_attendance_confirmation_types');
    }
};
