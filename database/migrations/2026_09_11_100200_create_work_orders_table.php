<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merged legacy `presupuestos` + `ots` → `work_orders`.
 *
 * Stage:
 * - estimate: status_id points to work_order_statuses.kind = estimate, confirmed_at null
 * - work_order: status_id points to work_order_statuses.kind = work_order, confirmed_at set
 *
 * Confirm in place (same row). Historical presupuesto+OT pairs stay two rows linked by source_work_order_id.
 *
 * Skipped (dead or other domains): notification flags, OOH, plantilla/informe FKs,
 * FileMaker ids, ot_padre boolean, tiempo_enviado, visits, chat, invoices, nora.
 * `ots.iteracion_id` → `contract_iteration_id` added in 2026_09_13_160200 (after contract_iterations).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('code', 64)->nullable();
            $table->string('subject');
            $table->string('reference')->nullable();
            $table->string('purchase_order')->nullable();
            $table->string('stage', 32);
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('source_work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->foreignId('status_id')->constrained('work_order_statuses')->restrictOnDelete();
            $table->foreignId('work_order_type_id')->nullable()->constrained('work_order_types')->nullOnDelete();
            $table->foreignId('client_priority_id')->nullable()->constrained('client_priorities')->nullOnDelete();
            $table->boolean('is_urgent')->default(false);
            $table->foreignId('establishment_id')->constrained('establishments')->restrictOnDelete();
            $table->foreignId('billing_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('requester_id')->nullable()->constrained('requesters')->nullOnDelete();
            $table->foreignId('delegation_id')->nullable()->constrained('delegations')->nullOnDelete();
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->foreignId('incident_id')->nullable()->constrained('incidents')->nullOnDelete();
            $table->foreignId('evaluation_id')->nullable()->constrained('evaluations')->nullOnDelete();
            $table->foreignId('parent_work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->foreignId('invoicing_work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->boolean('sync_grouping')->default(false);
            $table->boolean('is_intercompany')->default(false);
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->boolean('notes_alert')->default(false);
            $table->boolean('internal_notes_alert')->default(false);
            $table->boolean('is_reviewed')->default(false);
            $table->dateTime('received_at')->nullable();
            $table->dateTime('intervention_at')->nullable();
            $table->dateTime('assigned_at')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->dateTime('expected_close_at')->nullable();
            $table->dateTime('sla_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->dateTime('billed_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->boolean('received_at_overridden')->default(false);
            $table->text('sla_justification')->nullable();
            $table->boolean('is_sla_reviewed')->default(false);
            $table->decimal('tax_rate', 10, 2)->nullable();
            $table->boolean('tax_included')->default(false);
            $table->decimal('net_amount', 10, 2)->nullable();
            $table->decimal('tax_amount', 10, 2)->nullable();
            $table->decimal('total_amount', 10, 2)->nullable();
            $table->decimal('total_euros', 10, 2)->nullable();
            $table->decimal('cost_amount', 10, 2)->nullable();
            $table->decimal('margin_amount', 10, 2)->nullable();
            $table->decimal('profit_amount', 10, 2)->nullable();
            $table->dateTime('fx_rated_at')->nullable();
            $table->boolean('requires_billing_lines')->default(false);
            $table->boolean('notify_technician')->default(false);
            $table->unsignedInteger('organization_seconds')->nullable();
            $table->unsignedInteger('completion_seconds')->nullable();
            $table->string('legacy_erp_id', 64)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['stage', 'status_id']);
            $table->index(['establishment_id', 'stage']);
            $table->index('code');
            $table->index('legacy_erp_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};
