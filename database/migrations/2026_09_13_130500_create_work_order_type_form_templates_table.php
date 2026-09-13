<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy `ots_tipos_plantillas` → `work_order_type_form_templates`.
 * Drop modelo_id morph; typed owner FKs + work_order_type + template.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_type_form_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('owner_type', 32)->default('global');
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('company_relationship_id')->nullable()->constrained('company_relationships')->nullOnDelete();
            $table->foreignId('establishment_id')->nullable()->constrained('establishments')->nullOnDelete();
            $table->foreignId('form_bible_id')->nullable()->constrained('form_bibles')->nullOnDelete();
            $table->foreignId('work_order_type_id')->constrained('work_order_types')->cascadeOnDelete();
            $table->foreignId('form_template_id')->constrained('form_templates')->cascadeOnDelete();
            $table->timestamps();

            $table->index('owner_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_type_form_templates');
    }
};
