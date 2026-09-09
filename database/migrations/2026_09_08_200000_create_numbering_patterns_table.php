<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Company-scoped document/reference code patterns (contracts, invoices, …).
 * Segments are ordered rows: letters, symbols, year, sequence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('numbering_patterns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('resource', 64);
            $table->json('segments');
            $table->boolean('reset_yearly')->default(false);
            $table->unsignedInteger('last_sequence')->default(0);
            $table->unsignedSmallInteger('last_year')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'resource']);
            $table->index(['company_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('numbering_patterns');
    }
};
