<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timezones', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('timezone');
            $table->timestamps();
            $table->softDeletes();

            $table->unique('timezone');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreign('timezone_id')->references('id')->on('timezones')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['timezone_id']);
        });

        Schema::dropIfExists('timezones');
    }
};
