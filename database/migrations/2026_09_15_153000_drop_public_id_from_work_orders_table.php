<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('work_orders', 'public_id')) {
            return;
        }

        Schema::table('work_orders', function (Blueprint $table): void {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('work_orders', 'public_id')) {
            return;
        }

        Schema::table('work_orders', function (Blueprint $table): void {
            $table->uuid('public_id')->nullable()->unique()->after('id');
        });
    }
};
