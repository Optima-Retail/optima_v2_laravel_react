<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CostCenter;
use Illuminate\Database\Seeder;

/**
 * Cost centers from optimaback CentroCosteEnum.
 * Legacy IDs preserved for future data migration.
 */
final class CostCenterSeeder extends Seeder
{
    public function run(): void
    {
        $centers = [
            ['id' => 1, 'name' => 'MKT', 'code' => 'MKT'],
            ['id' => 2, 'name' => 'RRHH', 'code' => 'RRHH'],
            ['id' => 3, 'name' => 'FINANZAS', 'code' => 'FIN'],
            ['id' => 4, 'name' => 'OBRAS', 'code' => 'OBR'],
            ['id' => 5, 'name' => 'BENEFICIOS E', 'code' => 'BENE'],
            ['id' => 6, 'name' => 'OTROS', 'code' => 'OTRO'],
            ['id' => 7, 'name' => 'SUMINISTROS', 'code' => 'SUM'],
            ['id' => 8, 'name' => 'IT', 'code' => 'IT'],
            ['id' => 9, 'name' => 'VIAJES', 'code' => 'VIA'],
            ['id' => 10, 'name' => 'OFICINA', 'code' => 'OFI'],
        ];

        foreach ($centers as $center) {
            CostCenter::query()->updateOrCreate(
                ['id' => $center['id']],
                [
                    'name' => $center['name'],
                    'code' => $center['code'],
                ],
            );
        }
    }
}
