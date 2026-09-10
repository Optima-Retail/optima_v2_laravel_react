<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy `formas_de_pago` → `payment_methods`.
 * Kept: name, due_count (vencimiento), days (dias), code (codigo), soft deletes, timestamps.
 * Skipped: id_partner (unused / dead).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('due_count')->nullable();
            $table->unsignedInteger('days')->nullable();
            $table->string('code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
