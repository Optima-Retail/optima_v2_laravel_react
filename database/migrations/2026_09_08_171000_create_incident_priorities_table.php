<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy `incidencias_prioridades` → `incident_priorities`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_priorities', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('color', 32)->nullable();
            $table->unsignedInteger('resolution_time_hours');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_priorities');
    }
};
