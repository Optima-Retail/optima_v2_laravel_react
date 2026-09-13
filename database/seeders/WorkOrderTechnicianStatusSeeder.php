<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\WorkOrderTechnicianStatus;
use Illuminate\Database\Seeder;

/**
 * OT technician attendance from EstadoAsistenciaOtTecnicoEnum.
 * Legacy IDs preserved: 167 Pendiente, 168 Cancelada, 169 Confirmada.
 */
final class WorkOrderTechnicianStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['id' => 167, 'name' => 'Pendiente', 'color' => '#f6eac2'],
            ['id' => 168, 'name' => 'Cancelada', 'color' => '#edeae5'],
            ['id' => 169, 'name' => 'Confirmada', 'color' => '#97c1a9'],
        ];

        foreach ($statuses as $status) {
            WorkOrderTechnicianStatus::query()->updateOrCreate(
                ['id' => $status['id']],
                [
                    'name' => $status['name'],
                    'color' => $status['color'],
                ],
            );
        }
    }
}
