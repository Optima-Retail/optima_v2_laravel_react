<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Technician request priorities from legacy `tecnicos_prioridades`.
 * Kept: name, key (urgent|high|medium|low), color.
 * Due resolution is computed in TechnicianRequestService from key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_request_priorities', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('key', 32);
            $table->string('color', 32)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_request_priorities');
    }
};
