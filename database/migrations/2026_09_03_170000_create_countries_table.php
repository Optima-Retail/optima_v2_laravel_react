<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('iso_code', 2)->nullable();
            $table->foreignId('timezone_id')->nullable()->constrained('timezones')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('banks', function (Blueprint $table): void {
            $table->foreign('country_id')
                ->references('id')
                ->on('countries')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('banks', function (Blueprint $table): void {
            $table->dropForeign(['country_id']);
        });

        Schema::dropIfExists('countries');
    }
};
