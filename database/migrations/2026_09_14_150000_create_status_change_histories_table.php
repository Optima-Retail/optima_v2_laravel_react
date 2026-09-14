<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy historial_cambios_estados → typed document status audit.
 * document_type uses ChatDocumentType values (work_order, incident, …).
 * Status IDs point at the domain status catalog for that document type (not a shared estados table).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_change_histories', function (Blueprint $table): void {
            $table->id();
            $table->string('document_type', 64);
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('old_status_id')->nullable();
            $table->unsignedBigInteger('new_status_id');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('justification')->nullable();
            $table->decimal('price_decrease_amount', 10, 2)->nullable();
            $table->timestamps();

            $table->index(['document_type', 'document_id'], 'status_change_histories_document_index');
            $table->index('document_id', 'status_change_histories_document_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_change_histories');
    }
};
