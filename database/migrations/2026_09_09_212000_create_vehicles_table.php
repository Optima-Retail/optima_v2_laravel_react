<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vehicles from legacy `vehiculos`.
 * Kept: brand (marca), model (modelo), license_plate (matricula),
 * company_relationship_id (tecnico_id) → company_relationships where kind=technician.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table): void {
            $table->id();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('license_plate')->nullable();
            $table->foreignId('company_relationship_id')
                ->nullable()
                ->constrained('company_relationships')
                ->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
