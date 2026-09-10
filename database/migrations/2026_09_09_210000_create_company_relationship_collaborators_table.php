<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_relationship_collaborators', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_relationship_id');
            $table->foreignId('user_id');
            $table->timestamps();

            $table->foreign('company_relationship_id', 'crc_company_relationship_id_foreign')
                ->references('id')
                ->on('company_relationships')
                ->cascadeOnDelete();
            $table->foreign('user_id', 'crc_user_id_foreign')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->unique(['company_relationship_id', 'user_id'], 'crc_relationship_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_relationship_collaborators');
    }
};
