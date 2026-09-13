<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy costes_indirectos_tipo → indirect_cost_types.
 * Kept: nombre → name, codigo → code, color, timestamps.
 * Added: softDeletes (Config catalog convention).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indirect_cost_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code', 64);
            $table->string('color', 32)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indirect_cost_types');
    }
};
