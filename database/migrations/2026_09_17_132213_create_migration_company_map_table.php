<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('migration_company_map')) {
            return;
        }

        Schema::create('migration_company_map', function (Blueprint $table): void {
            $table->id();
            $table->string('legacy_source', 32);
            $table->string('legacy_id', 64);
            $table->unsignedBigInteger('new_id');
            $table->timestamps();

            $table->unique(['legacy_source', 'legacy_id'], 'mig_company_map_unique');
            $table->index('new_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_company_map');
    }
};
