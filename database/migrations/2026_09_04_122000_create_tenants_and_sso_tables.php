<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('sso_providers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('driver')->unique();
            $table->timestamps();
        });

        Schema::create('tenant_sso_provider_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('sso_provider_id')->constrained('sso_providers')->cascadeOnDelete();
            $table->string('key');
            $table->longText('value');
            $table->timestamps();

            $table->unique(['tenant_id', 'sso_provider_id', 'key'], 'tenant_sso_settings_unique');
            $table->index(['tenant_id', 'sso_provider_id'], 'tenant_sso_settings_lookup_idx');
        });

        Schema::create('user_sso_identities', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sso_provider_id')->constrained('sso_providers')->cascadeOnDelete();
            $table->string('external_id');
            $table->timestamps();

            $table->primary(['user_id', 'sso_provider_id']);
            $table->unique(['sso_provider_id', 'external_id'], 'user_sso_external_id_unique');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->nullable()->after('brand_id')->constrained('tenants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tenant_id');
        });

        Schema::dropIfExists('user_sso_identities');
        Schema::dropIfExists('tenant_sso_provider_settings');
        Schema::dropIfExists('sso_providers');
        Schema::dropIfExists('tenants');
    }
};
