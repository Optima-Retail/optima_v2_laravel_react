<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy other_expense_types (used by movements / purchase invoices).
 * Kept: name.
 * Added: softDeletes (Config catalog convention). Timestamps already in legacy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('other_expense_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('other_expense_types');
    }
};
