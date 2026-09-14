<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy `establecimiento_plantilla` → `establishment_form_template`.
 * Final shape: establishment + form template + work order type.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('establishment_form_template', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('establishment_id')->constrained('establishments')->cascadeOnDelete();
            $table->foreignId('form_template_id')->constrained('form_templates')->cascadeOnDelete();
            $table->foreignId('work_order_type_id')->constrained('work_order_types')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['establishment_id', 'form_template_id', 'work_order_type_id'],
                'establishment_form_template_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('establishment_form_template');
    }
};
