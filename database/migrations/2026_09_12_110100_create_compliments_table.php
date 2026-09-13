<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy `felicitaciones` → `compliments`.
 *
 * Replaces modelo_id + relacion_id morph with typed subject FKs:
 * brand | customer (company_relationships) | establishment.
 *
 * Dropped dead columns from Prod (asunto, usuario_id, establecimiento_id, puntuacion on header).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliments', function (Blueprint $table): void {
            $table->id();
            $table->string('subject_type', 32);
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('company_relationship_id')->nullable()->constrained('company_relationships')->nullOnDelete();
            $table->foreignId('establishment_id')->nullable()->constrained('establishments')->nullOnDelete();
            $table->foreignId('compliment_type_id')->constrained('compliment_types')->restrictOnDelete();
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('subject_type');
            $table->index(['subject_type', 'brand_id']);
            $table->index(['subject_type', 'company_relationship_id']);
            $table->index(['subject_type', 'establishment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliments');
    }
};
