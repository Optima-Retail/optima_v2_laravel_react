<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy centros_coste → cost_centers.
 * Kept: codigo → code, nombre → name.
 * Added: timestamps + softDeletes (Config catalog convention).
 * No dead columns in legacy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_centers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code', 64);
            $table->timestamps();
            $table->softDeletes();

            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_centers');
    }
};
