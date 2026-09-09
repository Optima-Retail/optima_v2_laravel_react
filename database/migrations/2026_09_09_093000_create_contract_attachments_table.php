<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contract-scoped attachments (legacy `archivos` filtered by Contrato).
 *
 * Kept (renamed):
 * - nombre → name
 * - ruta → path
 * - relacion_id → contract_id
 *
 * Access is controlled by permissions (view/upload/download/delete attachments),
 * not by per-row privado/borrable flags.
 *
 * Skipped: uuid, privado, borrable, modelo_id, canal, integracion_relacion_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->string('name');
            $table->string('path');
            $table->string('mime_type', 127)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_attachments');
    }
};
