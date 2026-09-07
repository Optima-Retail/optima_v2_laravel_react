<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('tradename')->nullable();
            $table->string('slug', 64);
            $table->string('tax_id', 32)->nullable();
            $table->string('kind', 32)->default('party');
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->foreignId('residence_country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->char('person_type', 1)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('website')->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->unsignedInteger('employee_count')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('logo')->nullable();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('language_id')->nullable()->constrained('languages')->nullOnDelete();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('legacy_erp_id', 64)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('slug');
            $table->unique('tax_id');
            $table->index('kind');
            $table->index('is_active');
            $table->index('legacy_erp_id');
        });

        Schema::create('company_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'user_id']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('active_company_id')->nullable()->after('brand_id')->constrained('companies')->nullOnDelete();
        });

        Schema::table('brands', function (Blueprint $table): void {
            $table->foreignId('corporation_company_id')->nullable()->after('name')->constrained('companies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('corporation_company_id');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('active_company_id');
        });

        Schema::dropIfExists('company_user');
        Schema::dropIfExists('companies');
    }
};
