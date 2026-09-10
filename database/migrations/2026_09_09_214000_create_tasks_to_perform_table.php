<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy `trabajos_a_realizar` → `tasks_to_perform` (checklist items for work orders / estimates).
 * No morph columns; document_type + document_id only (parents not migrated yet).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks_to_perform', function (Blueprint $table): void {
            $table->id();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->string('document_type');
            $table->unsignedBigInteger('document_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['document_type', 'document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks_to_perform');
    }
};
