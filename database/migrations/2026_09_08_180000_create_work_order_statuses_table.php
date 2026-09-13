<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shared statuses for estimates and work orders (legacy `estados` for Presupuesto + OT).
 * kind: estimate | work_order.
 * Kept: name, color, lifecycle (ciclo_vida), is_open (abierto).
 * Skipped: `estados_ot`, `modelo_id`, `codigo`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_statuses', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('kind', 32);
            $table->string('color', 32)->nullable();
            $table->unsignedTinyInteger('lifecycle')->nullable();
            $table->boolean('is_open')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('kind');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_statuses');
    }
};
