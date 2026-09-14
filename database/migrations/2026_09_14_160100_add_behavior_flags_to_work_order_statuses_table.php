<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Behavior flags for work-order / estimate statuses (config-driven, no hard-coded IDs).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_order_statuses', function (Blueprint $table): void {
            $table->boolean('is_default')->default(false)->after('is_open');
            $table->boolean('confirms_estimate')->default(false)->after('is_default');
            $table->boolean('rejects_to_estimate')->default(false)->after('confirms_estimate');
            $table->boolean('is_post_confirm_default')->default(false)->after('rejects_to_estimate');
            $table->boolean('sets_sent_at')->default(false)->after('is_post_confirm_default');
        });

        $this->flagByName('estimate', ['pendiente', 'pending'], ['is_default' => true]);
        $this->flagByName('estimate', ['aprobado', 'approved'], ['confirms_estimate' => true]);
        $this->flagByName('estimate', ['enviado a cliente', 'sent to client'], ['sets_sent_at' => true]);
        $this->flagByName('work_order', ['abierta - establecimiento', 'abierta'], ['is_default' => true]);
        $this->flagByName('work_order', ['recibida - ok por organizar', 'recibida'], ['is_post_confirm_default' => true]);
        $this->flagByName('work_order', ['rechazada - presupuesto', 'rechazada'], ['rejects_to_estimate' => true]);

        foreach (['estimate', 'work_order'] as $kind) {
            if (DB::table('work_order_statuses')->where('kind', $kind)->where('is_default', true)->exists()) {
                continue;
            }

            $fallbackId = DB::table('work_order_statuses')
                ->whereNull('deleted_at')
                ->where('kind', $kind)
                ->where('is_open', true)
                ->orderBy('lifecycle')
                ->orderBy('id')
                ->value('id');

            if ($fallbackId !== null) {
                DB::table('work_order_statuses')
                    ->where('id', $fallbackId)
                    ->update(['is_default' => true]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('work_order_statuses', function (Blueprint $table): void {
            $table->dropColumn([
                'is_default',
                'confirms_estimate',
                'rejects_to_estimate',
                'is_post_confirm_default',
                'sets_sent_at',
            ]);
        });
    }

    /**
     * @param  list<string>  $names
     * @param  array<string, bool>  $flags
     */
    private function flagByName(string $kind, array $names, array $flags): void
    {
        $query = DB::table('work_order_statuses')
            ->whereNull('deleted_at')
            ->where('kind', $kind)
            ->where(function ($builder) use ($names): void {
                foreach ($names as $name) {
                    $builder->orWhereRaw('LOWER(name) = ?', [$name]);
                }
            });

        $query->update($flags);
    }
};
