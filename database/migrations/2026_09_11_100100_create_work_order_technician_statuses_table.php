<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Technician attendance on a work order (`ot_tecnico.estado_id`).
 * Legacy estados 167 Pendiente, 168 Cancelada, 169 Confirmada — IDs preserved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_technician_statuses', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('color', 32)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_technician_statuses');
    }
};
