<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delegations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('tax_id', 64)->nullable();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->text('address')->nullable();
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->foreignId('series_id')->nullable()->constrained('series')->nullOnDelete();
            $table->boolean('cost_includes_vat')->default(false);
            $table->boolean('recovers_vat')->default(true);
            $table->json('billing_info')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delegations');
    }
};
