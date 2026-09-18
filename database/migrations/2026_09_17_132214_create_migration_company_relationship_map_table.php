<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('migration_company_relationship_map')) {
            return;
        }

        Schema::create('migration_company_relationship_map', function (Blueprint $table): void {
            $table->id();
            $table->string('legacy_source', 32);
            $table->unsignedBigInteger('legacy_id');
            $table->unsignedBigInteger('new_id');
            $table->string('kind', 32);
            $table->timestamps();

            $table->unique(['legacy_source', 'legacy_id'], 'mig_crel_map_unique');
            $table->index('new_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_company_relationship_map');
    }
};
