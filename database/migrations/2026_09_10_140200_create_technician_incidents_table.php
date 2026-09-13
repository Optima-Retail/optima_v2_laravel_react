<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Technician incidents from legacy `tecnicos_incidencias`.
 *
 * Kept:
 * - status_id → technician_incident_statuses
 * - technician_incident_type_id → technician_incident_types
 * - incident_text, response_text
 * - requested_by_id / responded_by_id / verified_by_id → users
 * - responded_at, verified_at, due_at
 * - technician_id → company_relationships (kind=technician)
 * - is_verified, negotiation_succeeded, unsuccessful_negotiation_solution
 *
 * Dead / do not import: abierta, num_facturas, num_facturas_reclamadas, invoice pivot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_incidents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('status_id')->nullable()->constrained('technician_incident_statuses')->nullOnDelete();
            $table->foreignId('technician_incident_type_id')->constrained('technician_incident_types');
            $table->text('incident_text')->nullable();
            $table->text('response_text')->nullable();
            $table->foreignId('requested_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('responded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('responded_at')->nullable();
            $table->foreignId('technician_id')->nullable()->constrained('company_relationships')->nullOnDelete();
            $table->boolean('is_verified')->default(false);
            $table->dateTime('verified_at')->nullable();
            $table->foreignId('verified_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('due_at')->nullable();
            $table->boolean('negotiation_succeeded')->nullable();
            $table->text('unsuccessful_negotiation_solution')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['technician_id', 'status_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_incidents');
    }
};
