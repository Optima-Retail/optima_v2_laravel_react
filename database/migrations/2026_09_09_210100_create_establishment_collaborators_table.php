<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('establishment_collaborators', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('establishment_id')->constrained('establishments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['establishment_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('establishment_collaborators');
    }
};
