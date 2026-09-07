<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\WorkOrderType;
use Illuminate\Database\Seeder;

/**
 * Active Optima work-order types (IDs 1–54), Spanish names from optimaback lang.
 * Excludes enum entries 55–62 that are not present in the current optimaback catalog UI.
 */
final class WorkOrderTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['id' => 1, 'name' => 'Obras/Reformas', 'code' => 'T01', 'color' => '#B8DCB8'],
            ['id' => 2, 'name' => 'Aire Acondicionado/Clima', 'code' => 'T02', 'color' => '#FF9999'],
            ['id' => 3, 'name' => 'Otros', 'code' => 'T03', 'color' => '#C7C7C7'],
            ['id' => 4, 'name' => 'Albañilería', 'code' => 'T04', 'color' => '#EFDD95'],
            ['id' => 5, 'name' => 'PCI', 'code' => 'T05', 'color' => '#FACAAB'],
            ['id' => 6, 'name' => 'Cristalería', 'code' => 'T06', 'color' => '#C8EDFF'],
            ['id' => 7, 'name' => 'Electricidad', 'code' => 'T07', 'color' => '#FFE897'],
            ['id' => 8, 'name' => 'PRL', 'code' => 'T08', 'color' => '#C3DFC5'],
            ['id' => 9, 'name' => 'Fontanería', 'code' => 'T09', 'color' => '#A5CEF4'],
            ['id' => 10, 'name' => 'IT', 'code' => 'T10', 'color' => '#C3DFC5'],
            ['id' => 11, 'name' => 'Pintura', 'code' => 'T11', 'color' => '#E8D0D0'],
            ['id' => 12, 'name' => 'Carpintería madera', 'code' => 'T12', 'color' => '#ECB98B'],
            ['id' => 13, 'name' => 'Imagen', 'code' => 'T13', 'color' => '#FF9999'],
            ['id' => 14, 'name' => 'Carpintería metálica', 'code' => 'T14', 'color' => '#D8E2E2'],
            ['id' => 15, 'name' => 'Cerrajería', 'code' => 'T15', 'color' => '#D7C3E2'],
            ['id' => 16, 'name' => 'Limpieza', 'code' => 'T16', 'color' => '#FAC7F7'],
            ['id' => 17, 'name' => 'Alumbrado', 'code' => 'T17', 'color' => '#EFF19A'],
            ['id' => 18, 'name' => 'Plagas', 'code' => 'T18', 'color' => '#BDE1BD'],
            ['id' => 19, 'name' => 'Cierre tienda', 'code' => 'T19', 'color' => '#C7C7C7'],
            ['id' => 20, 'name' => 'Lamps & Materials', 'code' => 'T20', 'color' => '#D8E2E2'],
            ['id' => 21, 'name' => 'Multitecnico', 'code' => 'T21', 'color' => '#D8E2E2'],
            ['id' => 22, 'name' => 'Facturación', 'code' => 'T22', 'color' => '#D8E2E2'],
            ['id' => 23, 'name' => 'Seguridad', 'code' => 'TO19', 'color' => '#B7BE7D'],
            ['id' => 24, 'name' => 'Coste AD', 'code' => 'T24', 'color' => '#B8DCB8'],
            ['id' => 25, 'name' => 'Incidencia QC', 'code' => 'T23', 'color' => '#BBBBBB'],
            ['id' => 26, 'name' => 'Visita fallida', 'code' => 'T26', 'color' => '#D8E2E2'],
            ['id' => 27, 'name' => 'Interior', 'code' => 'T27', 'color' => '#FAC7F7'],
            ['id' => 28, 'name' => 'Limpieza de ventanas (interior / exterior)', 'code' => 'T28', 'color' => '#FAC7F7'],
            ['id' => 29, 'name' => 'Limpieza exterior', 'code' => 'T29', 'color' => '#FAC7F7'],
            ['id' => 30, 'name' => 'Limpieza agrupada', 'code' => 'T30', 'color' => '#FAC7F7'],
            ['id' => 31, 'name' => 'Ascensores', 'code' => 'T31', 'color' => '#D36E70'],
            ['id' => 32, 'name' => 'Envío', 'code' => 'T32', 'color' => '#A0D8C5'],
            ['id' => 33, 'name' => 'Puertas automáticas', 'code' => 'T33', 'color' => '#B5C7E8'],
            ['id' => 34, 'name' => 'Pilonas/Barreras/Puertas exteriores', 'code' => 'T34', 'color' => '#E8C7B5'],
            ['id' => 35, 'name' => 'Certificación/Inspección', 'code' => 'T35', 'color' => '#C7E8B5'],
            ['id' => 36, 'name' => 'Felpudos', 'code' => 'T36', 'color' => '#D9C2A5'],
            ['id' => 37, 'name' => 'Puertas', 'code' => 'T37', 'color' => '#A5C2D9'],
            ['id' => 38, 'name' => 'Cargador coche eléctrico', 'code' => 'T38', 'color' => '#A0D8C5'],
            ['id' => 39, 'name' => 'Equipamiento/accesorios fijos', 'code' => 'T39', 'color' => '#C5C5A0'],
            ['id' => 40, 'name' => 'Suelos/Pavimentos', 'code' => 'T40', 'color' => '#D8C5B0'],
            ['id' => 41, 'name' => 'Generadores / Centros de transformación', 'code' => 'T41', 'color' => '#B0B0D8'],
            ['id' => 42, 'name' => 'Legionela', 'code' => 'T42', 'color' => '#A5D9D2'],
            ['id' => 43, 'name' => 'Taquillas y llaves', 'code' => 'T43', 'color' => '#D9A5C2'],
            ['id' => 44, 'name' => 'Puertas de radiofrecuencia / antihurto', 'code' => 'T44', 'color' => '#C2A5D9'],
            ['id' => 45, 'name' => 'Perchero/estantería móvil', 'code' => 'T45', 'color' => '#D9D2A5'],
            ['id' => 46, 'name' => 'Fosa séptica', 'code' => 'T46', 'color' => '#A5B89A'],
            ['id' => 47, 'name' => 'Limitador de sonido', 'code' => 'T47', 'color' => '#C9A5D9'],
            ['id' => 48, 'name' => 'Retirada de residuos', 'code' => 'T48', 'color' => '#9AB8A5'],
            ['id' => 49, 'name' => 'Dispensador/fuente de agua', 'code' => 'T49', 'color' => '#A5CFE8'],
            ['id' => 50, 'name' => 'Impermeabilización/Sellado', 'code' => 'T50', 'color' => '#B8A5D9'],
            ['id' => 51, 'name' => 'Líneas de vida', 'code' => 'T51', 'color' => '#E8B5B5'],
            ['id' => 52, 'name' => 'Señalética', 'code' => 'T52', 'color' => '#E8D5A5'],
            ['id' => 53, 'name' => 'Vinilos', 'code' => 'T53', 'color' => '#D5A5E8'],
            ['id' => 54, 'name' => 'Persiana/cierre metálico', 'code' => 'T54', 'color' => '#B0B8C0'],
        ];

        foreach ($types as $type) {
            WorkOrderType::query()->updateOrCreate(
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
