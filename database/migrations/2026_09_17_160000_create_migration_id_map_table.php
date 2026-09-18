<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('migration_id_map')) {
            return;
        }

        Schema::create('migration_id_map', function (Blueprint $table): void {
            $table->id();
            $table->string('entity', 64);
            $table->string('legacy_id', 64);
            $table->unsignedBigInteger('new_id');
            $table->timestamps();

            $table->unique(['entity', 'legacy_id'], 'mig_id_map_unique');
            $table->index('new_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_id_map');
    }
};
