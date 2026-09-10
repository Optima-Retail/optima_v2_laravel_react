<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy `documentos_de_pago` → `payment_documents`.
 * Kept: name, soft deletes, timestamps. No dead columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_documents');
    }
};
