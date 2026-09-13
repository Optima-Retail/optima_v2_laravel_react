<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot from legacy `peticiones_tipos_servicios`. No soft deletes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_request_service_type', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('technician_request_id')->constrained('technician_requests')->cascadeOnDelete();
            $table->foreignId('service_type_id')->constrained('service_types')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['technician_request_id', 'service_type_id'],
                'tr_service_type_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_request_service_type');
    }
};
