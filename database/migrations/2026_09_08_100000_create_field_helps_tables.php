<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_helps', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 191);
            $table->string('context', 191)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique('key');
            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('field_help_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('field_help_id')->constrained('field_helps')->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('example')->nullable();
            $table->timestamps();

            $table->unique(['field_help_id', 'locale']);
            $table->index('locale');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_help_translations');
        Schema::dropIfExists('field_helps');
    }
};
