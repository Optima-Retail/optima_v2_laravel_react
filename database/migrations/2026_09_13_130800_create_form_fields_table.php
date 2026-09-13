<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Legacy `campos_formularios` → `form_fields`. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_fields', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('form_section_id')->constrained('form_sections')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('type');
            $table->string('label')->nullable();
            $table->text('value')->nullable();
            $table->string('placeholder')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_repeatable')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_locked')->default(false);
            $table->boolean('is_cloned')->default(false);
            $table->json('payload')->nullable();
            $table->foreignId('task_to_perform_id')->nullable()->constrained('tasks_to_perform')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('form_fields')->nullOnDelete();
            $table->foreignId('conditional_field_id')->nullable()->constrained('form_fields')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['form_section_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_fields');
    }
};
