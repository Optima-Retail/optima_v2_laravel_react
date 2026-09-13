<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot from legacy `peticion_tecnico`.
 * company_relationship_id → company_relationships (kind=technician).
 * No soft deletes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_request_technician', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('technician_request_id')->constrained('technician_requests')->cascadeOnDelete();
            $table->foreignId('company_relationship_id')->constrained('company_relationships')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['technician_request_id', 'company_relationship_id'],
                'tr_technician_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_request_technician');
    }
};
