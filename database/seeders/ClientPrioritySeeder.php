<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ClientPriority;
use Illuminate\Database\Seeder;

/**
 * Catalog from legacy `prioridades` / PrioridadEnum.
 * Kept: name, code (clave), color, level (nivel). Dropped: id_partner, horas_vencimiento.
 * Level: lower = more urgent (Nora / ops convention).
 */
final class ClientPrioritySeeder extends Seeder
{
    public function run(): void
    {
        $priorities = [
            ['id' => 1, 'name' => 'Bajo Impacto', 'code' => 'P5', 'color' => '#95DBA1', 'level' => 5],
            ['id' => 2, 'name' => 'Medio Impacto', 'code' => 'P4', 'color' => '#EADF65', 'level' => 4],
            ['id' => 3, 'name' => 'Gran Impacto', 'code' => 'P3', 'color' => '#FFB847', 'level' => 2],
            ['id' => 4, 'name' => 'Urgencia', 'code' => 'P2', 'color' => '#FF6B6B', 'level' => 1],
            ['id' => 5, 'name' => 'Preventivo', 'code' => 'P6', 'color' => '#7D9DD6', 'level' => 4],
            ['id' => 6, 'name' => 'Incidencia QC', 'code' => null, 'color' => '#BE8DD6', 'level' => 3],
            ['id' => 7, 'name' => 'Urgencia Sábado', 'code' => 'P7', 'color' => '#FF6B6B', 'level' => 1],
            ['id' => 8, 'name' => 'Urgencia Domingo y Festivo', 'code' => 'P8', 'color' => '#FF6B6B', 'level' => 1],
            ['id' => 9, 'name' => 'Priority 1', 'code' => 'P9', 'color' => '#FF6B6B', 'level' => 1],
            ['id' => 10, 'name' => 'Priority 2', 'code' => 'P10', 'color' => '#FFB847', 'level' => 2],
            ['id' => 11, 'name' => 'Priority 3', 'code' => 'P11', 'color' => '#EADF65', 'level' => 3],
            ['id' => 12, 'name' => 'Priority 4', 'code' => 'P12', 'color' => '#95DBA1', 'level' => 4],
            ['id' => 13, 'name' => 'Priority 5', 'code' => 'P13', 'color' => '#7D9DD6', 'level' => 5],
            ['id' => 14, 'name' => 'Priority 6', 'code' => 'P14', 'color' => '#BE8DD6', 'level' => 6],
            ['id' => 15, 'name' => 'P1', 'code' => 'P15', 'color' => '#FF6B6B', 'level' => 1],
            ['id' => 16, 'name' => 'P2', 'code' => 'P16', 'color' => '#FFB847', 'level' => 2],
            ['id' => 17, 'name' => 'P3', 'code' => 'P17', 'color' => '#EADF65', 'level' => 3],
            ['id' => 18, 'name' => 'P4', 'code' => 'P18', 'color' => '#95DBA1', 'level' => 4],
            ['id' => 19, 'name' => 'P5', 'code' => 'P19', 'color' => '#7D9DD6', 'level' => 5],
            ['id' => 20, 'name' => 'ADDL REQU', 'code' => 'P20', 'color' => '#BE8DD6', 'level' => 3],
            ['id' => 21, 'name' => 'Urgencia 24H', 'code' => 'P21', 'color' => '#FF6B6B', 'level' => 1],
        ];

        foreach ($priorities as $priority) {
            ClientPriority::query()->updateOrCreate(
                ['id' => $priority['id']],
                [
                    'name' => $priority['name'],
                    'code' => $priority['code'],
                    'color' => $priority['color'],
                    'level' => $priority['level'],
                ],
            );
        }
    }
}
