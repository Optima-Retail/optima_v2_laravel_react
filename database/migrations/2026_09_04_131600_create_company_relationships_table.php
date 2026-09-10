<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_relationships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('related_company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('kind', 32);
            $table->string('status', 32)->default('active');
            $table->string('classification', 50)->default('commercial');
            $table->string('owner_reference', 80)->nullable();
            $table->string('related_reference', 80)->nullable();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('delegation_id')->nullable()->constrained('delegations')->nullOnDelete();
            $table->foreignId('billing_language_id')->nullable()->constrained('languages')->nullOnDelete();
            $table->foreignId('series_id')->nullable()->constrained('series')->nullOnDelete();
            $table->foreignId('integration_id')->nullable()->constrained('integrations')->nullOnDelete();
            $table->string('integration_external_id', 80)->nullable();
            $table->foreignId('reported_customer_relationship_id')->nullable()->constrained('company_relationships')->nullOnDelete();
            $table->foreignId('corrective_work_order_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('preventive_work_order_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('quality_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('account_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('commercial_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sourced_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('external_code', 80)->nullable();
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->boolean('notes_alert')->default(false);
            $table->boolean('internal_notes_alert')->default(false);
            $table->text('onboarding_notes')->nullable();
            $table->text('billing_comments')->nullable();
            $table->text('rates_notes')->nullable();
            $table->text('archetype')->nullable();
            $table->decimal('tax_rate', 10, 2)->nullable();
            $table->boolean('is_reviewed')->default(false);
            $table->boolean('is_email_reviewed')->default(false);
            $table->boolean('is_invoicing_reviewed')->default(false);
            $table->timestamp('invoicing_reviewed_at')->nullable();
            $table->unsignedInteger('quote_close_days')->nullable();
            $table->unsignedInteger('recurring_meeting_frequency')->nullable();
            $table->unsignedInteger('sales_feedback_meeting_frequency')->nullable();
            $table->boolean('group_zero_cost_work_orders')->default(false);
            $table->boolean('load_materials_on_corrective')->default(false);
            $table->boolean('group_preventive_and_corrective')->default(false);
            $table->string('group_preventives_by', 32)->nullable();
            $table->string('group_correctives_by', 32)->nullable();
            $table->boolean('invoice_at_month_end')->default(false);
            $table->boolean('requires_purchase_order')->default(false);
            $table->boolean('requires_requester')->default(false);
            $table->boolean('is_franchise')->default(false);
            $table->boolean('requires_justification')->default(false);
            $table->boolean('auto_send_invoices')->default(false);
            $table->boolean('send_invoices_individually')->default(false);
            $table->boolean('send_debt_reminders')->default(false);
            $table->boolean('is_quality_control_contactable')->default(false);
            $table->boolean('requires_client_informed_check')->default(false);
            $table->boolean('requires_intervention_scheduled_check')->default(false);
            $table->boolean('requires_budget_approval_limit')->default(false);
            $table->boolean('is_intercompany')->default(false);
            $table->decimal('optima_score', 10, 2)->nullable();
            $table->decimal('customer_score', 10, 2)->nullable();
            $table->decimal('average_score', 10, 2)->nullable();
            $table->unsignedInteger('optima_score_count')->default(0);
            $table->unsignedInteger('customer_score_count')->default(0);
            $table->boolean('has_health_and_safety')->default(false);
            $table->boolean('is_field_technician')->default(false);
            $table->boolean('is_creditor')->default(false);
            $table->boolean('is_vip')->default(false);
            $table->boolean('is_available_24h')->default(false);
            $table->time('day_start_at')->nullable();
            $table->time('day_end_at')->nullable();
            $table->boolean('has_garnishment')->default(false);
            $table->boolean('whatsapp_messaging_authorized')->default(false);
            $table->timestamp('registered_at')->nullable();
            $table->unsignedBigInteger('legacy_status_id')->nullable();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('deleted_token', 64)->default('');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['owner_company_id', 'related_company_id', 'kind', 'deleted_token'], 'company_relationships_owner_related_kind_unique');
            $table->index(['owner_company_id', 'status']);
            $table->index(['related_company_id', 'kind']);
            $table->index('integration_external_id');
        });

        DB::statement('ALTER TABLE company_relationships ADD CONSTRAINT company_relationships_no_self CHECK (owner_company_id <> related_company_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('company_relationships');
    }
};
