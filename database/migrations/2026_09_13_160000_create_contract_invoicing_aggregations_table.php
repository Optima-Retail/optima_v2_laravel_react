<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy `contract_invoicing_aggregations` (billing groups for contract iterations).
 *
 * `per_establecimiento` → `per_establishment`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_invoicing_aggregations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->string('subject', 200)->nullable();
            $table->string('billing_frequency', 16)->default('monthly');
            $table->unsignedTinyInteger('billing_day')->nullable();
            $table->date('billing_cycle_start')->nullable();
            $table->boolean('per_establishment')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('contract_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_invoicing_aggregations');
    }
};
