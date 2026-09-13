<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy `presupuestos_lineas_facturacion` + `ots_lineas_facturacion` → `work_order_lines`.
 *
 * Kept: articulo_id, descripcion, cantidad, precio_unitario, precio_unitario_euros, base_imponible, orden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();
            $table->string('description')->nullable();
            $table->decimal('quantity', 12, 3)->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('unit_price_euros', 10, 2)->nullable();
            $table->decimal('net_amount', 10, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['work_order_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_lines');
    }
};
