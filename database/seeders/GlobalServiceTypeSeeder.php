<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\GlobalServiceType;
use Illuminate\Database\Seeder;

/**
 * Legacy tipos_servicios_globales / TipoServicioGlobalEnum catalog.
 * IDs preserved 1–23.
 */
final class GlobalServiceTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['id' => 1, 'name' => 'Obras'],
            ['id' => 2, 'name' => 'Clima'],
            ['id' => 3, 'name' => 'Iluminación'],
            ['id' => 4, 'name' => 'Toldos'],
            ['id' => 5, 'name' => 'Tapiceros'],
            ['id' => 6, 'name' => 'Andamios'],
            ['id' => 7, 'name' => 'Pantallas Led'],
            ['id' => 8, 'name' => 'Contenedores'],
            ['id' => 9, 'name' => 'Gruas'],
            ['id' => 10, 'name' => 'Transportes'],
            ['id' => 11, 'name' => 'Certif. PCI'],
            ['id' => 12, 'name' => 'Certif. Electricidad'],
            ['id' => 13, 'name' => 'Certif. Arquitectura'],
            ['id' => 14, 'name' => 'Seguridad'],
            ['id' => 15, 'name' => 'Estructuras Metalicas'],
            ['id' => 16, 'name' => 'Marketing'],
            ['id' => 17, 'name' => 'Suministros Electricidad'],
            ['id' => 18, 'name' => 'Suministros Clima'],
            ['id' => 19, 'name' => 'Suministros Cerrajeria'],
            ['id' => 20, 'name' => 'Suministros Fontaneria'],
            ['id' => 21, 'name' => 'Audiovisuales'],
            ['id' => 22, 'name' => 'Suministros Generales'],
            ['id' => 23, 'name' => 'Plagas'],
        ];

        foreach ($types as $type) {
            GlobalServiceType::query()->updateOrCreate(
                ['id' => $type['id']],
                [
                    'name' => $type['name'],
                    'color' => '#fcba03',
                    'code' => null,
                ],
            );
        }
    }
}
