<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy tipos_servicios_globales → global_service_types.
 *
 * Kept: nombre→name, clave→code, color→color.
 * Soft deletes added for v2 catalog consistency (legacy had none).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_service_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('color', 32)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_service_types');
    }
};
