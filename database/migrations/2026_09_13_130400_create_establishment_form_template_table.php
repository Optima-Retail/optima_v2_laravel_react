<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Legacy `establecimiento_plantilla` → `establishment_form_template`. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('establishment_form_template', function (Blueprint $table): void {
            $table->foreignId('establishment_id')->constrained('establishments')->cascadeOnDelete();
            $table->foreignId('form_template_id')->constrained('form_templates')->cascadeOnDelete();
            $table->primary(['establishment_id', 'form_template_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('establishment_form_template');
    }
};
