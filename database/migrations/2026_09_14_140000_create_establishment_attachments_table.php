<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy polymorphic `archivos` (establecimiento) → `establishment_attachments`.
 *
 * Kept: name, path, mime/size, privado → is_private, uploaded_by.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('establishment_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('establishment_id')->constrained('establishments')->cascadeOnDelete();
            $table->string('name');
            $table->string('path');
            $table->string('mime_type', 127)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->boolean('is_private')->default(false);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['establishment_id', 'is_private']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('establishment_attachments');
    }
};
