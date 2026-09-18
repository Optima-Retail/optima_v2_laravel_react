<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('migration_brand_map')) {
            return;
        }

        Schema::create('migration_brand_map', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('legacy_id');
            $table->unsignedBigInteger('new_id');
            $table->timestamps();

            $table->unique('legacy_id');
            $table->index('new_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_brand_map');
    }
};
