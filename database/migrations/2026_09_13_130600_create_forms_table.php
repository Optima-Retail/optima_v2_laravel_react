<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy `formularios` → `forms`.
 * Morph → typed subject (work_order | technician).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forms', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 32)->unique();
            $table->string('name')->nullable();
            $table->foreignId('form_type_id')->nullable()->constrained('form_types')->nullOnDelete();
            $table->foreignId('form_status_id')->nullable()->constrained('form_statuses')->nullOnDelete();
            $table->foreignId('language_id')->nullable()->constrained('languages')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('form_template_id')->nullable()->constrained('form_templates')->nullOnDelete();
            $table->string('subject_type', 32);
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->foreignId('company_relationship_id')->nullable()->constrained('company_relationships')->nullOnDelete();
            $table->date('occurred_on')->nullable();
            $table->unsignedTinyInteger('app_platform_id')->default(1);
            $table->string('technician_code')->nullable();
            $table->boolean('was_edited')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('subject_type');
            $table->index(['subject_type', 'work_order_id']);
            $table->index(['subject_type', 'company_relationship_id']);
            $table->index('form_status_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forms');
    }
};
