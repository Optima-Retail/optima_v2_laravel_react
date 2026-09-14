<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_status_transitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('from_status_id')->constrained('work_order_statuses')->cascadeOnDelete();
            $table->foreignId('to_status_id')->constrained('work_order_statuses')->cascadeOnDelete();
            $table->boolean('requires_confirmation')->default(false);
            $table->boolean('requires_justification')->default(false);
            $table->timestamps();

            $table->unique(['from_status_id', 'to_status_id'], 'work_order_status_transitions_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_status_transitions');
    }
};
