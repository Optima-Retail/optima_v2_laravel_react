<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Legacy `secciones_formularios` → `form_sections`. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('label')->nullable();
            $table->boolean('is_repeatable')->default(false);
            $table->boolean('is_modal')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_cloned')->default(false);
            $table->foreignId('form_template_section_id')->nullable()->constrained('form_template_sections')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('form_sections')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['form_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_sections');
    }
};
