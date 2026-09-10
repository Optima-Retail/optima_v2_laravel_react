<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy ots_tipos_tipos_servicios → work_order_type_service_types.
 *
 * Kept:
 * - ot_tipo_id → work_order_type_id
 * - tipo_servicio_id → service_type_id
 * - timestamps + softDeletes
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_type_service_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('work_order_type_id')
                ->constrained('work_order_types')
                ->cascadeOnDelete();
            $table->foreignId('service_type_id')
                ->constrained('service_types')
                ->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['work_order_type_id', 'service_type_id'],
                'wots_work_order_type_service_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_type_service_types');
    }
};
