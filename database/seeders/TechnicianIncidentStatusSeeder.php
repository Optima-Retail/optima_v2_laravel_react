<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\TechnicianIncidentStatus;
use Illuminate\Database\Seeder;

/**
 * Technician incident statuses from optimaback EstadosTecnicoIncidenciaEnum.
 * Legacy IDs preserved for future data migration.
 */
final class TechnicianIncidentStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['id' => 96, 'name' => 'Abierta', 'color' => null, 'lifecycle' => 1, 'is_open' => true],
            ['id' => 97, 'name' => 'En Progreso', 'color' => null, 'lifecycle' => 2, 'is_open' => true],
            ['id' => 98, 'name' => 'Cerrada', 'color' => null, 'lifecycle' => 3, 'is_open' => false],
            ['id' => 155, 'name' => 'Verificada', 'color' => null, 'lifecycle' => null, 'is_open' => false],
            ['id' => 156, 'name' => 'Cancelada', 'color' => null, 'lifecycle' => null, 'is_open' => false],
            ['id' => 173, 'name' => 'Borrador', 'color' => null, 'lifecycle' => null, 'is_open' => true],
        ];

        foreach ($statuses as $status) {
            $model = TechnicianIncidentStatus::withTrashed()->find($status['id'])
                ?? new TechnicianIncidentStatus;

            $model->forceFill($status)->save();
        }
    }
}
