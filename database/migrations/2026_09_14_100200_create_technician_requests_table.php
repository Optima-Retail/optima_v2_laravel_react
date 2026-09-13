<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Technician requests from legacy `peticiones`.
 *
 * Kept:
 * - company_id (active operating company)
 * - is_screening (filtraje)
 * - parent_technician_request_id (peticion_id)
 * - work_order_id (ot_id)
 * - code, description, notes, internal_notes
 * - city, postal_code, address_line, province_name
 * - country_id, language_id
 * - requester_user_id, responsible_user_id
 * - resolved_at, due_at, next_action_at
 * - technician_request_priority_id, technician_request_status_id
 *
 * Deferred: chat, mails, direccion_2, auto-create technician party, OT assign modal, reports.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->boolean('is_screening')->default(false);
            $table->foreignId('parent_technician_request_id')
                ->nullable()
                ->constrained('technician_requests')
                ->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->string('address_line')->nullable();
            $table->string('province_name')->nullable();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->foreignId('language_id')->nullable()->constrained('languages')->nullOnDelete();
            $table->foreignId('requester_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->dateTime('next_action_at')->nullable();
            $table->foreignId('technician_request_priority_id')
                ->nullable()
                ->constrained('technician_request_priorities')
                ->nullOnDelete();
            $table->foreignId('technician_request_status_id')
                ->nullable()
                ->constrained('technician_request_statuses')
                ->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'is_screening'], 'tr_company_screening_idx');
            $table->index(['company_id', 'technician_request_status_id'], 'tr_company_status_idx');
            $table->index(['company_id', 'technician_request_priority_id'], 'tr_company_priority_idx');
            $table->index(['company_id', 'responsible_user_id'], 'tr_company_responsible_idx');
            $table->index('due_at', 'tr_due_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_requests');
    }
};
