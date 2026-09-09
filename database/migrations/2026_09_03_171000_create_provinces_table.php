<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Professional catalog for administrative divisions (legacy `provincias`).
 * Linked to countries; companies and establishments reference province_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('provinces')) {
            return;
        }

        Schema::create('provinces', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 10)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['country_id', 'name']);
            $table->unique(['country_id', 'code']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provinces');
    }
};
