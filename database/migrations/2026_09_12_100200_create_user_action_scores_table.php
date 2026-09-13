<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy `accion_usuarios` → `user_action_scores`.
 *
 * Records a scored KPI event for a user against a document.
 * Legacy modelo_id + relacion_id → document_type + document_id
 * (estimate | work_order → work_orders.id; other types later).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_action_scores', function (Blueprint $table): void {
            $table->id();
            $table->decimal('weight', 12, 4);
            $table->decimal('max_value', 12, 4);
            $table->decimal('value', 12, 4);
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('action_id')->constrained('actions')->restrictOnDelete();
            $table->string('document_type', 32);
            $table->unsignedBigInteger('document_id');
            $table->timestamps();

            $table->index(['document_type', 'document_id']);
            $table->index(['user_id', 'action_id']);
            $table->index(['action_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_action_scores');
    }
};
