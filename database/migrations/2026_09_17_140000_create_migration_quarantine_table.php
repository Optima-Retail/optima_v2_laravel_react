<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('migration_quarantine')) {
            return;
        }

        Schema::create('migration_quarantine', function (Blueprint $table): void {
            $table->id();
            $table->string('entity', 64);
            $table->string('legacy_id', 64)->nullable();
            $table->string('field', 64)->nullable();
            $table->string('action', 64);
            $table->text('reason');
            $table->text('original_value')->nullable();
            $table->timestamps();

            $table->index(['entity', 'legacy_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_quarantine');
    }
};
