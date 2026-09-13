<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy checklists → checklists (status-gated validation templates).
 *
 * Kept:
 * - texto → label
 * - validar → requires_validation
 * - orden → sort_order
 * - timestamps
 *
 * Replaced:
 * - modelo_id → document_type (work_order | estimate)
 * - estado_id → work_order_status_id (shared statuses table, filtered by kind)
 *
 * Soft deletes added for Config catalog convention.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklists', function (Blueprint $table): void {
            $table->id();
            $table->text('label');
            $table->boolean('requires_validation')->default(true);
            $table->string('document_type', 32);
            $table->foreignId('work_order_status_id')
                ->constrained('work_order_statuses')
                ->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['document_type', 'work_order_status_id'], 'checklists_wo_status_idx');
            $table->index('sort_order');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE checklists ADD CONSTRAINT checklists_document_type_ck CHECK (
                    document_type IN ('work_order', 'estimate')
                )",
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('checklists');
    }
};
