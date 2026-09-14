<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Behavior flags for technician incident statuses (config-driven, no hard-coded IDs).
 *
 * - is_default: used when creating a new incident
 * - marks_verified: target of verify action; enter/leave side effects
 * - sets_response_date: stamps responded_at when entering this status
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technician_incident_statuses', function (Blueprint $table): void {
            $table->boolean('is_default')->default(false)->after('is_open');
            $table->boolean('marks_verified')->default(false)->after('is_default');
            $table->boolean('sets_response_date')->default(false)->after('marks_verified');
        });

        // Best-effort backfill by localized name (legacy seed data). Safe if rows are missing.
        $this->flagByName(['abierta', 'open'], ['is_default' => true]);
        $this->flagByName(['verificada', 'verified'], [
            'marks_verified' => true,
            'sets_response_date' => true,
        ]);
        $this->flagByName(['cerrada', 'closed'], ['sets_response_date' => true]);

        if (! DB::table('technician_incident_statuses')->where('is_default', true)->exists()) {
            $fallbackId = DB::table('technician_incident_statuses')
                ->whereNull('deleted_at')
                ->where('is_open', true)
                ->orderBy('lifecycle')
                ->orderBy('id')
                ->value('id');

            if ($fallbackId !== null) {
                DB::table('technician_incident_statuses')
                    ->where('id', $fallbackId)
                    ->update(['is_default' => true]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('technician_incident_statuses', function (Blueprint $table): void {
            $table->dropColumn(['is_default', 'marks_verified', 'sets_response_date']);
        });
    }

    /**
     * @param  list<string>  $names
     * @param  array<string, bool>  $flags
     */
    private function flagByName(array $names, array $flags): void
    {
        $query = DB::table('technician_incident_statuses')->whereNull('deleted_at');

        $query->where(function ($builder) use ($names): void {
            foreach ($names as $name) {
                $builder->orWhereRaw('LOWER(name) = ?', [$name]);
            }
        });

        $query->update($flags);
    }
};
