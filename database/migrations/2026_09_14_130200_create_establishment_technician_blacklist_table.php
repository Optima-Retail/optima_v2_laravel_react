<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy `establecimiento_tecnico` → `establishment_technician_blacklist`
 * (blocked technicians for an establishment).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('establishment_technician_blacklist', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('establishment_id');
            $table->unsignedBigInteger('company_relationship_id');
            $table->timestamps();

            $table->foreign('establishment_id', 'est_tech_bl_est_fk')
                ->references('id')
                ->on('establishments')
                ->cascadeOnDelete();
            $table->foreign('company_relationship_id', 'est_tech_bl_rel_fk')
                ->references('id')
                ->on('company_relationships')
                ->cascadeOnDelete();

            $table->unique(
                ['establishment_id', 'company_relationship_id'],
                'est_tech_blacklist_est_rel_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('establishment_technician_blacklist');
    }
};
