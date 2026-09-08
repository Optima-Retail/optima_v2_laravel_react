<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\EstablishmentType;
use Illuminate\Database\Seeder;

/**
 * Legacy TipoEstablecimientoEnum catalog (optimaback tipos_establecimiento).
 */
final class EstablishmentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'id' => 1,
                'name' => 'Tienda',
                'code' => 'store',
                'health_and_safety_delay_days' => 5,
            ],
            [
                'id' => 2,
                'name' => 'ECI',
                'code' => 'eci',
                'health_and_safety_delay_days' => 1,
            ],
            [
                'id' => 3,
                'name' => 'CC',
                'code' => 'cc',
                'health_and_safety_delay_days' => 4,
            ],
        ];

        foreach ($types as $type) {
            EstablishmentType::query()->updateOrCreate(
                ['id' => $type['id']],
                [
                    'name' => $type['name'],
                    'code' => $type['code'],
                    'health_and_safety_delay_days' => $type['health_and_safety_delay_days'],
                ],
            );
        }
    }
}
