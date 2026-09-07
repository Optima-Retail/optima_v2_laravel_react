<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->foreignId('account_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('commercial_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('loyalty_meeting_frequency')->nullable();
            $table->boolean('is_quality_control_contactable')->default(true);
            $table->boolean('send_debt_reminders')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique('name');
        });

        Schema::create('brand_collaborators', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['brand_id', 'user_id']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreign('brand_id')->references('id')->on('brands')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['brand_id']);
        });

        Schema::dropIfExists('brand_collaborators');
        Schema::dropIfExists('brands');
    }
};
