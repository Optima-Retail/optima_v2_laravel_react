<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy `articulo_idiomas` → `article_languages` (per-language name/description).
 * No timestamps (legacy has none).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_languages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('language_id')->constrained('languages')->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();

            $table->unique(['article_id', 'language_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_languages');
    }
};
