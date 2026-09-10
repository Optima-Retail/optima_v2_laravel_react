<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy `horarios` → `company_schedules` (1:1 with client company).
 * Kept: all weekday open/close times + company_id, soft deletes, timestamps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->time('monday_opens')->nullable();
            $table->time('monday_closes')->nullable();
            $table->time('tuesday_opens')->nullable();
            $table->time('tuesday_closes')->nullable();
            $table->time('wednesday_opens')->nullable();
            $table->time('wednesday_closes')->nullable();
            $table->time('thursday_opens')->nullable();
            $table->time('thursday_closes')->nullable();
            $table->time('friday_opens')->nullable();
            $table->time('friday_closes')->nullable();
            $table->time('saturday_opens')->nullable();
            $table->time('saturday_closes')->nullable();
            $table->time('sunday_opens')->nullable();
            $table->time('sunday_closes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_schedules');
    }
};
