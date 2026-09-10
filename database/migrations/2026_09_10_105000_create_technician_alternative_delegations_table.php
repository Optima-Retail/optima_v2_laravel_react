<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy delegacion_tecnico → technician_alternative_delegations.
 *
 * Kept:
 * - tecnico_id → company_relationship_id (company_relationships kind=technician)
 * - delegacion_id → delegation_id
 * - timestamps + softDeletes
 *
 * Alternative delegations alongside the primary delegation_id on the relationship.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_alternative_delegations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_relationship_id');
            $table->unsignedBigInteger('delegation_id');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_relationship_id', 'tad_relationship_fk')
                ->references('id')
                ->on('company_relationships')
                ->cascadeOnDelete();
            $table->foreign('delegation_id', 'tad_delegation_fk')
                ->references('id')
                ->on('delegations')
                ->cascadeOnDelete();

            $table->index(
                ['company_relationship_id', 'delegation_id'],
                'tad_relationship_delegation_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_alternative_delegations');
    }
};
