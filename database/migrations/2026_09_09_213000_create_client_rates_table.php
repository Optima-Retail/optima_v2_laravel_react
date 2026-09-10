<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy `tarifas` → `client_rates` (per-client rate matrix by priority + work order type).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_relationship_id')->constrained('company_relationships')->restrictOnDelete();
            $table->foreignId('client_priority_id')->constrained('client_priorities')->restrictOnDelete();
            $table->foreignId('work_order_type_id')->constrained('work_order_types')->restrictOnDelete();
            $table->decimal('travel_amount', 10, 2)->default(0);
            $table->decimal('extra_travel_amount', 10, 2)->default(0);
            $table->decimal('labor_amount', 10, 2)->default(0);
            $table->decimal('extra_labor_amount', 10, 2)->default(0);
            $table->unsignedInteger('due_hours')->default(0);
            $table->unsignedInteger('sla_hours')->default(0);
            $table->boolean('is_urgent')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['company_relationship_id', 'client_priority_id', 'work_order_type_id'],
                'client_rates_relationship_priority_wo_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_rates');
    }
};
