<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy `tipos_felicitacion` → `compliment_types`.
 * Catalog IDs preserved via seeder (1 Rapidez, 2 Amabilidad, 3 Gestión).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliment_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliment_types');
    }
};
