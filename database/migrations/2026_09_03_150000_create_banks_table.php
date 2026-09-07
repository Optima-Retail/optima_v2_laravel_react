<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banks', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->string('legal_name', 255)->nullable();
            // Reserved for countries. Index without FK for now.
            $table->unsignedBigInteger('country_id')->nullable();
            $table->string('swift_bic', 11)->nullable();
            $table->string('national_bank_code', 20)->nullable();
            $table->string('lei', 20)->nullable();
            $table->string('supervisor_code', 40)->nullable();
            $table->string('website', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('country_id');
            $table->index('swift_bic');
            $table->index('national_bank_code');
            $table->index('is_active');
            $table->unique(['country_id', 'national_bank_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banks');
    }
};
