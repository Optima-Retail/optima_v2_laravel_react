<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shared statuses for estimates and work orders (legacy `estados` for Presupuesto + OT).
 * kind: estimate | work_order.
 * Kept: name, color, lifecycle (ciclo_vida), is_open (abierto).
 * Behavior flags: is_default, confirms_estimate, rejects_to_estimate,
 * is_post_confirm_default, sets_sent_at.
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
            $table->boolean('is_default')->default(false);
            $table->boolean('confirms_estimate')->default(false);
            $table->boolean('rejects_to_estimate')->default(false);
            $table->boolean('is_post_confirm_default')->default(false);
            $table->boolean('sets_sent_at')->default(false);
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
