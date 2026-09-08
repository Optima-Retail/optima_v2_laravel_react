<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Professional catalog for administrative divisions (legacy `provincias`).
 * Linked to countries; companies and establishments reference province_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provinces', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 10)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['country_id', 'name']);
            $table->unique(['country_id', 'code']);
            $table->index('name');
        });

        Schema::table('companies', function (Blueprint $table): void {
            $table->foreignId('province_id')
                ->nullable()
                ->after('city')
                ->constrained('provinces')
                ->nullOnDelete();
        });

        Schema::table('establishments', function (Blueprint $table): void {
            $table->foreignId('province_id')
                ->nullable()
                ->after('city')
                ->constrained('provinces')
                ->nullOnDelete();
        });

        $this->backfillProvinceIds('companies');
        $this->backfillProvinceIds('establishments');

        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn('province');
        });

        Schema::table('establishments', function (Blueprint $table): void {
            $table->dropColumn('province');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('province')->nullable()->after('city');
        });

        Schema::table('establishments', function (Blueprint $table): void {
            $table->string('province')->nullable()->after('city');
        });

        $this->restoreProvinceNames('companies');
        $this->restoreProvinceNames('establishments');

        Schema::table('companies', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('province_id');
        });

        Schema::table('establishments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('province_id');
        });

        Schema::dropIfExists('provinces');
    }

    private function backfillProvinceIds(string $table): void
    {
        if (! Schema::hasColumn($table, 'province')) {
            return;
        }

        $rows = DB::table($table)
            ->select(['id', 'province', 'country_id'])
            ->whereNotNull('province')
            ->where('province', '!=', '')
            ->get();

        foreach ($rows as $row) {
            $query = DB::table('provinces')
                ->whereNull('deleted_at')
                ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $row->province))]);

            if ($row->country_id !== null) {
                $query->where('country_id', $row->country_id);
            }

            $provinceId = $query->value('id');

            if ($provinceId === null) {
                continue;
            }

            DB::table($table)->where('id', $row->id)->update(['province_id' => $provinceId]);
        }
    }

    private function restoreProvinceNames(string $table): void
    {
        $rows = DB::table($table)
            ->select(["{$table}.id", 'provinces.name'])
            ->join('provinces', 'provinces.id', '=', "{$table}.province_id")
            ->whereNotNull("{$table}.province_id")
            ->get();

        foreach ($rows as $row) {
            DB::table($table)->where('id', $row->id)->update(['province' => $row->name]);
        }
    }
};
