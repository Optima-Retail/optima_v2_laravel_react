<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Form statuses from legacy `formularios_estados` (EstadoFormularioEnum).
 * Kept: name (nombre), next_status_id (siguiente_id) — workflow chain.
 * Skipped: orden (in fillable in legacy model but never migrated / not in schema).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_statuses', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->foreignId('next_status_id')
                ->nullable()
                ->constrained('form_statuses')
                ->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_statuses');
    }
};
