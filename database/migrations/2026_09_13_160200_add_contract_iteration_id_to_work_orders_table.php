<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy `ots.iteracion_id` → `work_orders.contract_iteration_id`.
 *
 * Separate from create_work_orders because `contract_iterations` is created later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->foreignId('contract_iteration_id')
                ->nullable()
                ->after('contract_id')
                ->constrained('contract_iterations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('contract_iteration_id');
        });
    }
};
