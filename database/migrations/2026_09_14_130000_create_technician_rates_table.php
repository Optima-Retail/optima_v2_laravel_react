<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy `tecnicos_tarifas` → `technician_rates` (one rate card per technician relationship).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_relationship_id')
                ->unique()
                ->constrained('company_relationships')
                ->cascadeOnDelete();
            $table->decimal('labor_weekday_amount', 10, 2)->default(0);
            $table->decimal('labor_night_amount', 10, 2)->default(0);
            $table->decimal('labor_weekend_amount', 10, 2)->default(0);
            $table->decimal('labor_holiday_amount', 10, 2)->default(0);
            $table->decimal('labor_urgent_amount', 10, 2)->default(0);
            $table->decimal('travel_weekday_amount', 10, 2)->default(0);
            $table->decimal('travel_night_amount', 10, 2)->default(0);
            $table->decimal('travel_weekend_amount', 10, 2)->default(0);
            $table->decimal('travel_holiday_amount', 10, 2)->default(0);
            $table->decimal('travel_urgent_amount', 10, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_rates');
    }
};
