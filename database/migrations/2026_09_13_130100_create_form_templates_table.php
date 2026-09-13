<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy `plantillas` → `form_templates`.
 * Morph modelo_id+relacion_id → typed owner_type + FKs. Bigint PK (no string id).
 * Scoped by company_id (active company).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('form_type_id')->constrained('form_types')->restrictOnDelete();
            $table->foreignId('language_id')->nullable()->constrained('languages')->nullOnDelete();
            $table->boolean('is_default')->default(false);
            $table->foreignId('work_order_type_id')->nullable()->constrained('work_order_types')->nullOnDelete();
            $table->string('owner_type', 32)->default('global');
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('company_relationship_id')->nullable()->constrained('company_relationships')->nullOnDelete();
            $table->foreignId('establishment_id')->nullable()->constrained('establishments')->nullOnDelete();
            $table->foreignId('form_bible_id')->nullable()->constrained('form_bibles')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('owner_type');
            $table->index(['owner_type', 'brand_id']);
            $table->index(['owner_type', 'company_relationship_id']);
            $table->index(['owner_type', 'establishment_id']);
            $table->index(['owner_type', 'form_bible_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_templates');
    }
};
