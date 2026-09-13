<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\IndirectCostType;
use Illuminate\Database\Seeder;

/**
 * Indirect cost types from optimaback CosteIndirectoTipoEnum.
 * Legacy IDs preserved for future data migration.
 */
final class IndirectCostTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['id' => 1, 'name' => 'Empresa', 'code' => 'CIE', 'color' => '#FF5733'],
            ['id' => 2, 'name' => 'Societario', 'code' => 'CIS', 'color' => '#33FF57'],
            ['id' => 3, 'name' => 'Informático', 'code' => 'CII', 'color' => '#33FF57'],
            ['id' => 4, 'name' => 'Pisos', 'code' => 'CIP', 'color' => '#33FF57'],
        ];

        foreach ($types as $type) {
            IndirectCostType::query()->updateOrCreate(
                ['id' => $type['id']],
                [
                    'name' => $type['name'],
                    'code' => $type['code'],
                    'color' => $type['color'],
                ],
            );
        }
    }
}
