<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy tipos_servicios → service_types.
 *
 * Kept: nombre→name, clave→code, color→color.
 * Used for technician skills, WO type↔service matrix, and technician requests.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_types', function (Blueprint $table): void {
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
        Schema::dropIfExists('service_types');
    }
};
