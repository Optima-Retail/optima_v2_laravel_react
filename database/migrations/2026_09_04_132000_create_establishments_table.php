<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('establishments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 64)->nullable();
            $table->string('store_code', 64)->nullable();
            $table->string('alternate_store_code', 64)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->text('emails')->nullable();
            $table->text('recipient_emails')->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->foreignId('timezone_id')->nullable()->constrained('timezones')->nullOnDelete();
            $table->foreignId('language_id')->nullable()->constrained('languages')->nullOnDelete();
            $table->foreignId('establishment_type_id')->nullable()->constrained('establishment_types')->nullOnDelete();
            $table->foreignId('delegation_id')->nullable()->constrained('delegations')->nullOnDelete();
            $table->foreignId('series_id')->nullable()->constrained('series')->nullOnDelete();
            $table->foreignId('billing_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_client_priority')->default(false);
            $table->boolean('is_reviewed')->default(false);
            $table->boolean('is_email_reviewed')->default(false);
            $table->boolean('has_site_health_and_safety')->default(false);
            $table->boolean('has_customer_health_and_safety')->default(false);
            $table->boolean('is_quality_control_contactable')->default(false);
            $table->boolean('has_parking')->default(false);
            $table->boolean('is_ulez_zone')->default(false);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('tax_rate', 10, 2)->nullable();
            $table->boolean('tax_included')->default(false);
            $table->string('legacy_erp_id', 64)->nullable();
            $table->string('integration_external_id', 80)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('notes_alert')->default(false);
            $table->text('internal_notes')->nullable();
            $table->boolean('internal_notes_alert')->default(false);
            $table->json('voicebot_time_slots')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active']);
            $table->index('store_code');
            $table->index('legacy_erp_id');
            $table->index('integration_external_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('establishments');
    }
};
