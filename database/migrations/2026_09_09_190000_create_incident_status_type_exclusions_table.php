<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Acciones: which statuses are unavailable for which incident types.
 * Managed from Config → Incident statuses (no hard-coded IDs in app code).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_status_type_exclusions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('incident_status_id')->constrained('incident_statuses')->cascadeOnDelete();
            $table->foreignId('incident_type_id')->constrained('incident_types')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['incident_status_id', 'incident_type_id'],
                'incident_status_type_exclusions_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_status_type_exclusions');
    }
};
