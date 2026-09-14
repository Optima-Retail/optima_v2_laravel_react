<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy `valoraciones_tecnico` → `technician_ratings`.
 *
 * `origen_id` / `origenes` collapsed into string `source` (`optima` | `customer`).
 * Uniqueness for nullable `work_order_id` is enforced in the service via updateOrCreate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_ratings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_relationship_id')
                ->constrained('company_relationships')
                ->cascadeOnDelete();
            $table->foreignId('work_order_id')
                ->nullable()
                ->constrained('work_orders')
                ->nullOnDelete();
            $table->unsignedTinyInteger('score');
            $table->text('notes')->nullable();
            $table->string('source', 32);
            $table->timestamps();

            $table->unique(
                ['company_relationship_id', 'work_order_id', 'source'],
                'technician_ratings_relationship_wo_source_unique',
            );
            $table->index(['company_relationship_id', 'source'], 'technician_ratings_relationship_source_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_ratings');
    }
};
