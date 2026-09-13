<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy `archivos` for felicitaciones (modelo morph) → typed `compliment_attachments`.
 * Skipped: uuid, privado, borrable, modelo_id, canal, integracion_relacion_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliment_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('compliment_id')->constrained('compliments')->cascadeOnDelete();
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
        Schema::dropIfExists('compliment_attachments');
    }
};
