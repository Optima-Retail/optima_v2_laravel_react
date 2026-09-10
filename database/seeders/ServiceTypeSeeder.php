<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ServiceType;
use Illuminate\Database\Seeder;

/**
 * Legacy tipos_servicios / TipoServicioEnum catalog.
 * IDs preserved (gaps 27–28, 35 match legacy).
 */
final class ServiceTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['id' => 1, 'name' => 'Climatización y frio industrial', 'color' => '#49be25'],
            ['id' => 2, 'name' => 'Fontaneria', 'color' => '#be4d25'],
            ['id' => 3, 'name' => 'Manitas', 'color' => '#9925be'],
            ['id' => 4, 'name' => 'Multiservicios', 'color' => '#2587be'],
            ['id' => 5, 'name' => 'PCI', 'color' => '#d6d81c'],
            ['id' => 6, 'name' => 'Albañilería', 'color' => '#c71cd8'],
            ['id' => 7, 'name' => 'Pintura', 'color' => '#d88c1c'],
            ['id' => 8, 'name' => 'Carpintería metálica', 'color' => '#7fd81c'],
            ['id' => 9, 'name' => 'Carpintería de madera', 'color' => '#1cb4d8'],
            ['id' => 10, 'name' => 'Decoración', 'color' => '#fd8181'],
            ['id' => 11, 'name' => 'Cerrajería', 'color' => '#b5bbc5'],
            ['id' => 12, 'name' => 'Cristalería', 'color' => '#42b9f5'],
            ['id' => 13, 'name' => 'Plagas', 'color' => '#711e05'],
            ['id' => 14, 'name' => 'Limpieza', 'color' => '#c7fbf5'],
            ['id' => 15, 'name' => 'IT', 'color' => '#fbc7ec'],
            ['id' => 16, 'name' => 'Gráficas', 'color' => '#ddfbc7'],
            ['id' => 17, 'name' => 'Jardinería', 'color' => '#f3cb45'],
            ['id' => 18, 'name' => 'Moquetas', 'color' => '#f5427e'],
            ['id' => 19, 'name' => 'Audiovisuales', 'color' => '#bd6628'],
            ['id' => 20, 'name' => 'Electricidad', 'color' => '#5e5126'],
            ['id' => 21, 'name' => 'Puertas automáticas', 'color' => '#ab913e'],
            ['id' => 22, 'name' => 'Automatismos', 'color' => '#a6508d'],
            ['id' => 23, 'name' => 'Desatascos', 'color' => '#8a6b36'],
            ['id' => 24, 'name' => 'Trabajos verticales', 'color' => '#f0dab6'],
            ['id' => 25, 'name' => 'Gestión de residuos', 'color' => '#9f9bb0'],
            ['id' => 26, 'name' => 'Seguridad', 'color' => '#d6d81c'],
            ['id' => 29, 'name' => 'Suministros', 'color' => '#858a94'],
            ['id' => 30, 'name' => 'Lacado de muebles', 'color' => '#a028b5'],
            ['id' => 31, 'name' => 'Reparacion electrodom.', 'color' => '#cc1455'],
            ['id' => 32, 'name' => 'Transporte/Mudanza', 'color' => '#2a7cd2'],
            ['id' => 33, 'name' => 'Impermeabilizaciones', 'color' => '#8eab1d'],
            ['id' => 34, 'name' => 'Limpieza de vidrios', 'color' => '#410c28'],
            ['id' => 36, 'name' => 'Control de legionela', 'color' => '#a70f86'],
            ['id' => 37, 'name' => 'CCTV / Cámaras de seguridad', 'color' => '#3a11b9'],
            ['id' => 38, 'name' => 'Aire Comprimido', 'color' => '#c9a729'],
            ['id' => 39, 'name' => 'Ascensores', 'color' => '#D36E70'],
            ['id' => 40, 'name' => 'Caldera', 'color' => '#CE8B4D'],
        ];

        foreach ($types as $type) {
            ServiceType::query()->updateOrCreate(
                ['id' => $type['id']],
                [
                    'name' => $type['name'],
                    'color' => $type['color'],
                    'code' => null,
                ],
            );
        }
    }
}
