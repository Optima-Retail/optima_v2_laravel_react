<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy `historial_qcoins` → `quality_score_ledger`.
 * Balance movements applied to users.quality_score.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quality_score_ledger', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('caused_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('action_id')->constrained('actions')->restrictOnDelete();
            $table->decimal('previous_score', 12, 2);
            $table->decimal('new_score', 12, 2);
            $table->decimal('delta', 12, 2);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['action_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_score_ledger');
    }
};
