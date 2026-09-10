<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\IncidentStatus;
use App\Models\IncidentType;
use Illuminate\Database\Seeder;

/**
 * Incident statuses from optimaback EstadosIncidenciasEnum (estados where modelo = Incidencia).
 * Legacy IDs preserved for future data migration.
 */
final class IncidentStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['id' => 89, 'name' => 'Abierta - QC', 'color' => '#f6eac2', 'lifecycle' => 1, 'is_open' => true],
            ['id' => 91, 'name' => 'En revisión - QC', 'color' => '#c7dbda', 'lifecycle' => 2, 'is_open' => true],
            ['id' => 92, 'name' => 'En espera - QC', 'color' => '#ffdac1', 'lifecycle' => 2, 'is_open' => true],
            ['id' => 94, 'name' => 'Finalizada - QC', 'color' => '#c5e1a5', 'lifecycle' => 3, 'is_open' => false],
            ['id' => 150, 'name' => 'Feedback Sales - QC', 'color' => '#a9cef0', 'lifecycle' => 2, 'is_open' => false],
            ['id' => 170, 'name' => 'Cancelada - QC', 'color' => '#bdbdbd', 'lifecycle' => null, 'is_open' => false],
        ];

        foreach ($statuses as $status) {
            IncidentStatus::query()->updateOrCreate(
                ['id' => $status['id']],
                [
                    'name' => $status['name'],
                    'color' => $status['color'],
                    'lifecycle' => $status['lifecycle'],
                    'is_open' => $status['is_open'],
                ],
            );
        }

        // Optima rule: Feedback Sales is not selectable in Acciones for Controllers type.
        $feedbackSales = IncidentStatus::query()->find(150);
        $controllers = IncidentType::query()->find(13);

        if ($feedbackSales !== null && $controllers !== null) {
            $feedbackSales->excludedTypes()->syncWithoutDetaching([$controllers->id]);
        }
    }
}
