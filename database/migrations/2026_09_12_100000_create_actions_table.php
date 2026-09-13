<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy `acciones` → `actions` (QCoins / quality-score action catalog).
 * IDs preserved via seeder (AccionEnum 1–15).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actions', function (Blueprint $table): void {
            $table->id();
            $table->string('weight_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actions');
    }
};
