<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Technician request / screening statuses from legacy polymorphic `estados`.
 * Kept: kind (request|screening), name, color, lifecycle, is_open.
 * Legacy IDs preserved via seeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_request_statuses', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 32);
            $table->string('name');
            $table->string('color', 32)->nullable();
            $table->unsignedTinyInteger('lifecycle')->nullable();
            $table->boolean('is_open')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['kind', 'is_open']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_request_statuses');
    }
};
