<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy tecnicos_tipos_servicios → technician_service_types.
 *
 * Kept:
 * - tecnico_id → company_relationship_id (company_relationships kind=technician)
 * - tipo_servicio_id → service_type_id
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_service_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_relationship_id')
                ->constrained('company_relationships')
                ->cascadeOnDelete();
            $table->foreignId('service_type_id')
                ->constrained('service_types')
                ->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_relationship_id', 'service_type_id'], 'tst_relationship_service_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_service_types');
    }
};
