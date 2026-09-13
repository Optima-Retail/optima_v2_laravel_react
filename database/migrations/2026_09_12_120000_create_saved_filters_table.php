<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy `filtros` → `saved_filters`.
 *
 * Replaces modelo_id morph with page_key (string resource key for main lists only).
 * Config catalog indexes do not use this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_filters', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('page_key', 64);
            $table->json('filters');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'page_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_filters');
    }
};
