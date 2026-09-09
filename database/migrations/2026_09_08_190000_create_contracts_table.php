<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contracts from legacy `contratos`.
 *
 * Kept (renamed):
 * - codigo → code
 * - cliente_id → company_id (client company)
 * - responsable_id → responsible_user_id
 * - progreso → progress
 * - total_progreso → total_progress
 * - importe_total → total_amount
 * - descripcion → description
 * - estado_id → contract_status_id
 * - idioma_id → language_id
 * - asunto_ot → work_order_subject
 * - fecha_firma → signed_at
 * - canceled_date → canceled_at
 *
 * Skipped (dropped in legacy): coste, ots_tipo_id, seguimiento, fecha_inicio, fecha_fin.
 * Index start/end dates in optimafront were OT-derived, not contract columns.
 *
 * Pivot: contratos_establecimientos → contract_establishment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->nullable();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('contract_status_id')->nullable()->constrained('contract_statuses')->nullOnDelete();
            $table->foreignId('language_id')->nullable()->constrained('languages')->nullOnDelete();
            $table->string('description')->nullable();
            $table->string('work_order_subject')->nullable();
            $table->unsignedInteger('progress')->nullable();
            $table->unsignedInteger('total_progress')->nullable();
            $table->decimal('total_amount', 10, 2)->nullable();
            $table->dateTime('signed_at')->nullable();
            $table->dateTime('canceled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('code');
            $table->index(['company_id', 'contract_status_id']);
        });

        Schema::create('contract_establishment', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->foreignId('establishment_id')->constrained('establishments')->cascadeOnDelete();

            $table->unique(['contract_id', 'establishment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_establishment');
        Schema::dropIfExists('contracts');
    }
};
