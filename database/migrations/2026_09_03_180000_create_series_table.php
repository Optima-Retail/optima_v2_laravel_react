<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('series', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64);
            $table->string('color')->default('#ffffff');
            $table->boolean('is_selectable')->default(true);
            $table->unsignedBigInteger('credit_note_series_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('key');
        });

        Schema::table('series', function (Blueprint $table): void {
            $table->foreign('credit_note_series_id')
                ->references('id')
                ->on('series')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('series');
    }
};
