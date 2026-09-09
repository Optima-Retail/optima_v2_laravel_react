<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\IncidentPriority;
use Illuminate\Database\Seeder;

/**
 * Catalog from legacy `incidencias_prioridades` / IncidenciaPrioridadEnum.
 */
final class IncidentPrioritySeeder extends Seeder
{
    public function run(): void
    {
        $priorities = [
            ['id' => 1, 'name' => 'Alto impacto', 'color' => '#FF9999', 'resolution_time_hours' => 4],
            ['id' => 2, 'name' => 'Medio impacto', 'color' => '#EFF19A', 'resolution_time_hours' => 8],
        ];

        foreach ($priorities as $priority) {
            IncidentPriority::query()->updateOrCreate(
                ['id' => $priority['id']],
                [
                    'name' => $priority['name'],
                    'color' => $priority['color'],
                    'resolution_time_hours' => $priority['resolution_time_hours'],
                ],
            );
        }
    }
}
