<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy `tipos_incidencia` → `incident_types`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('color', 32)->nullable();
            $table->foreignId('default_priority_id')
                ->nullable()
                ->default(2)
                ->constrained('incident_priorities')
                ->nullOnDelete();
            $table->boolean('origin_selectable')->default(false);
            $table->json('origin_options')->nullable();
            $table->string('default_origin_type')->nullable();
            $table->boolean('origin_required')->default(true);
            $table->string('related_type')->nullable();
            $table->boolean('show_related')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_types');
    }
};
