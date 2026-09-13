<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy `kpi_configuraciones` → `kpi_configurations`.
 * Maps raw metric values to a 0–1 score percentage per action.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_configurations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('action_id')->constrained('actions')->cascadeOnDelete();
            $table->decimal('min_value', 12, 4)->nullable();
            $table->decimal('max_value', 12, 4)->nullable();
            $table->decimal('percentage', 8, 4);
            $table->timestamps();

            $table->index(['action_id', 'min_value', 'max_value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_configurations');
    }
};
