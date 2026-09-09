<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy `incidencia_qc_subtipos` → `incident_subtypes`.
 *
 * Skipped: `es_externo` — present in legacy schema/seed only; no runtime usage in prod app logic.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_subtypes', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->foreignId('incident_type_id')
                ->constrained('incident_types')
                ->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('incident_type_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_subtypes');
    }
};
