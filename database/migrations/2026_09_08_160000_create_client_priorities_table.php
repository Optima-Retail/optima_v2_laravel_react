<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy `prioridades` → `client_priorities` (client company catalog).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_priorities', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('color', 32)->nullable();
            $table->unsignedTinyInteger('level')->default(3);
            $table->timestamps();
            $table->softDeletes();

            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_priorities');
    }
};
