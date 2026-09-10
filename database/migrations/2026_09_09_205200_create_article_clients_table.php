<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy `articulo_cliente` → `article_clients` (per-client sale price).
 * Hard-delete preferred on sync to avoid unique conflicts (no soft deletes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_clients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('company_relationship_id')->constrained('company_relationships')->restrictOnDelete();
            $table->decimal('sale_price', 10, 2);
            $table->timestamps();

            $table->unique(['article_id', 'company_relationship_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_clients');
    }
};
