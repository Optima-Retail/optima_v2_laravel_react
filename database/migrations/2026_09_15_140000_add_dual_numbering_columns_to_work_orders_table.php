<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Split work_orders numbering into estimate_* and work_order_* slots, plus
 * identity flags is_estimate / is_work_order (at least one must be true).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('work_orders', 'is_estimate')) {
                $table->boolean('is_estimate')->default(false)->after('code');
            }

            if (! Schema::hasColumn('work_orders', 'is_work_order')) {
                $table->boolean('is_work_order')->default(false)->after('is_estimate');
            }

            if (! Schema::hasColumn('work_orders', 'estimate_num')) {
                $table->string('estimate_num', 64)->nullable()->after('is_work_order');
            }

            if (! Schema::hasColumn('work_orders', 'estimate_num_cardinal')) {
                $table->unsignedInteger('estimate_num_cardinal')->nullable()->after('estimate_num');
            }

            if (! Schema::hasColumn('work_orders', 'estimate_numbering_pattern_id')) {
                $table->foreignId('estimate_numbering_pattern_id')->nullable()->after('estimate_num_cardinal')
                    ->constrained('numbering_patterns')->nullOnDelete();
            }

            if (! Schema::hasColumn('work_orders', 'estimate_old_num')) {
                $table->string('estimate_old_num', 64)->nullable()->after('estimate_numbering_pattern_id');
            }

            if (! Schema::hasColumn('work_orders', 'work_order_num')) {
                $table->string('work_order_num', 64)->nullable()->after('estimate_old_num');
            }

            if (! Schema::hasColumn('work_orders', 'work_order_num_cardinal')) {
                $table->unsignedInteger('work_order_num_cardinal')->nullable()->after('work_order_num');
            }

            if (! Schema::hasColumn('work_orders', 'work_order_numbering_pattern_id')) {
                $table->foreignId('work_order_numbering_pattern_id')->nullable()->after('work_order_num_cardinal')
                    ->constrained('numbering_patterns')->nullOnDelete();
            }

            if (! Schema::hasColumn('work_orders', 'work_order_old_num')) {
                $table->string('work_order_old_num', 64)->nullable()->after('work_order_numbering_pattern_id');
            }
        });

        DB::table('work_orders')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                $stage = (string) $row->stage;
                $code = $row->code !== null && $row->code !== '' ? (string) $row->code : null;
                $confirmed = $row->confirmed_at !== null;

                if ($stage === 'estimate') {
                    DB::table('work_orders')->where('id', $row->id)->update([
                        'is_estimate' => true,
                        'is_work_order' => false,
                        'estimate_num' => $code,
                        'work_order_num' => null,
                    ]);

                    continue;
                }

                DB::table('work_orders')->where('id', $row->id)->update([
                    'is_estimate' => $confirmed,
                    'is_work_order' => true,
                    'work_order_num' => $code,
                    'estimate_num' => null,
                ]);
            }
        });

        $indexes = Schema::getIndexes('work_orders');
        $names = array_column($indexes, 'name');

        Schema::table('work_orders', function (Blueprint $table) use ($names): void {
            if (! in_array('work_orders_estimate_num_index', $names, true)) {
                $table->index('estimate_num');
            }

            if (! in_array('work_orders_work_order_num_index', $names, true)) {
                $table->index('work_order_num');
            }

            if (! in_array('work_orders_is_estimate_is_work_order_index', $names, true)) {
                $table->index(['is_estimate', 'is_work_order']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table): void {
            if (Schema::hasColumn('work_orders', 'estimate_numbering_pattern_id')) {
                $table->dropConstrainedForeignId('estimate_numbering_pattern_id');
            }

            if (Schema::hasColumn('work_orders', 'work_order_numbering_pattern_id')) {
                $table->dropConstrainedForeignId('work_order_numbering_pattern_id');
            }

            $drop = array_values(array_filter([
                Schema::hasColumn('work_orders', 'is_estimate') ? 'is_estimate' : null,
                Schema::hasColumn('work_orders', 'is_work_order') ? 'is_work_order' : null,
                Schema::hasColumn('work_orders', 'estimate_num') ? 'estimate_num' : null,
                Schema::hasColumn('work_orders', 'estimate_num_cardinal') ? 'estimate_num_cardinal' : null,
                Schema::hasColumn('work_orders', 'estimate_old_num') ? 'estimate_old_num' : null,
                Schema::hasColumn('work_orders', 'work_order_num') ? 'work_order_num' : null,
                Schema::hasColumn('work_orders', 'work_order_num_cardinal') ? 'work_order_num_cardinal' : null,
                Schema::hasColumn('work_orders', 'work_order_old_num') ? 'work_order_old_num' : null,
            ]));

            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
