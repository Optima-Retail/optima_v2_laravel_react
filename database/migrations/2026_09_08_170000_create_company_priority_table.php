<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy `clientes_prioridades` → `company_priority`.
 * Kept: company_id (cliente_id), client_priority_id (prioridad_id), timestamps, softDeletes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_priority', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('client_priority_id')->constrained('client_priorities')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'client_priority_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_priority');
    }
};
