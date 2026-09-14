<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy `establecimiento_tecnico_fav` → `establishment_favorite_technicians`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('establishment_favorite_technicians', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('establishment_id');
            $table->unsignedBigInteger('company_relationship_id');
            $table->timestamps();

            $table->foreign('establishment_id', 'est_tech_fav_est_fk')
                ->references('id')
                ->on('establishments')
                ->cascadeOnDelete();
            $table->foreign('company_relationship_id', 'est_tech_fav_rel_fk')
                ->references('id')
                ->on('company_relationships')
                ->cascadeOnDelete();

            $table->unique(
                ['establishment_id', 'company_relationship_id'],
                'est_tech_favorites_est_rel_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('establishment_favorite_technicians');
    }
};
