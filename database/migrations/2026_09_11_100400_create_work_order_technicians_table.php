<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy `presupuestos_solicitados` + `ot_tecnico` → `work_order_technicians`.
 *
 * tecnico_id → company_relationship_id (kind=technician).
 * Quote money is used on estimates; attendance status on confirmed work orders.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_technicians', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignId('company_relationship_id')->constrained('company_relationships')->restrictOnDelete();
            $table->boolean('is_selected')->default(false);
            $table->text('description')->nullable();
            $table->foreignId('status_id')->nullable()->constrained('work_order_technician_statuses')->nullOnDelete();
            $table->foreignId('attendance_confirmation_type_id')
                ->nullable()
                ->constrained('technician_attendance_confirmation_types')
                ->nullOnDelete();
            $table->decimal('quote_net_amount', 10, 2)->nullable();
            $table->decimal('quote_tax_amount', 10, 2)->nullable();
            $table->decimal('quote_total_amount', 10, 2)->nullable();
            $table->decimal('quote_total_euros', 10, 2)->nullable();
            $table->decimal('tax_rate', 10, 2)->nullable();
            $table->boolean('tax_included')->default(false);
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->foreignId('delegation_id')->nullable()->constrained('delegations')->nullOnDelete();
            $table->string('quote_document_path')->nullable();
            $table->uuid('public_id')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['work_order_id', 'company_relationship_id'], 'work_order_technicians_wo_tech_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_technicians');
    }
};
