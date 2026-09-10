<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy `articulos` → `articles`.
 * Kept: code (codigo), is_deletable (borrable), soft deletes, timestamps.
 * Skipped: cliente_id, tipo_id, precio_venta, nombre, descripcion (moved to related tables).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50);
            $table->boolean('is_deletable')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
