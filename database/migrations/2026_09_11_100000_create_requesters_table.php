<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy `solicitantes` → `requesters`.
 *
 * Kept: nombre_completo → name, correos → emails, cliente_id → company_id (party).
 * Soft deletes added (legacy table had none).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requesters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->json('emails')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requesters');
    }
};
