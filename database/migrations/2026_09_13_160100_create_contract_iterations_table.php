<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy `iteraciones` — contract recurrence schedules.
 *
 * See docs/data-migration/14-contract-iterations.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_iterations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->foreignId('work_order_type_id')->constrained('work_order_types')->restrictOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('periodicity', 16);
            $table->string('periodicity_kind', 16);
            $table->unsignedInteger('interval')->nullable();
            $table->json('weekdays')->nullable();
            $table->json('month_days')->nullable();
            $table->json('months')->nullable();
            $table->decimal('cost_amount', 10, 2);
            $table->string('subject', 200)->nullable();
            $table->json('establishment_ids')->nullable();
            $table->foreignId('form_template_id')->nullable()->constrained('form_templates')->nullOnDelete();
            $table->foreignId('invoicing_aggregation_id')
                ->nullable()
                ->constrained('contract_invoicing_aggregations')
                ->nullOnDelete();
            $table->timestamps();

            $table->index('contract_id');
            $table->index('work_order_type_id');
            $table->index('invoicing_aggregation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_iterations');
    }
};
