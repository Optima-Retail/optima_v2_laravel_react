<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy `chequeos` (OT / presupuesto) → `work_order_checklist_completions`.
 *
 * relacion_id + checklist.modelo → work_order_id.
 * validado → is_validated, observaciones → notes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_checklist_completions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignId('checklist_id')->constrained('checklists')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->boolean('is_validated')->default(false);
            $table->timestamps();

            $table->unique(['work_order_id', 'checklist_id'], 'work_order_checklist_completions_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_checklist_completions');
    }
};
