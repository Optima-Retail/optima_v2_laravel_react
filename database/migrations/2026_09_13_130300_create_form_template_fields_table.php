<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Legacy `campos_plantillas` → `form_template_fields`. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_template_fields', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('form_template_section_id')->constrained('form_template_sections')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('type');
            $table->string('label')->nullable();
            $table->text('default_value')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_repeatable')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_locked')->default(false);
            $table->boolean('is_cloned')->default(false);
            $table->json('payload')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('form_template_fields')->nullOnDelete();
            $table->foreignId('conditional_field_id')->nullable()->constrained('form_template_fields')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['form_template_section_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_template_fields');
    }
};
