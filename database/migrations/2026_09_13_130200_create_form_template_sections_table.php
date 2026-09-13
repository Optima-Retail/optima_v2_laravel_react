<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Legacy `secciones_plantillas` → `form_template_sections`. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_template_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('form_template_id')->constrained('form_templates')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('label')->nullable();
            $table->boolean('is_repeatable')->default(false);
            $table->boolean('is_modal')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->foreignId('parent_id')->nullable()->constrained('form_template_sections')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['form_template_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_template_sections');
    }
};
