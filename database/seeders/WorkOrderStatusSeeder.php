<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\WorkOrderStatus;
use Illuminate\Database\Seeder;

/**
 * OT statuses from optimaback EstadoOTEnum (estados where modelo = OT).
 * Legacy IDs preserved for future data migration.
 */
final class WorkOrderStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['id' => 10, 'name' => 'Abierta - Establecimiento', 'color' => '#f6eac2', 'lifecycle' => 1, 'is_open' => true],
            ['id' => 11, 'name' => 'Cerrada - Cancelada', 'color' => '#edeae5', 'lifecycle' => 2, 'is_open' => false],
            ['id' => 12, 'name' => 'Rechazada - Presupuesto', 'color' => '#edeae5', 'lifecycle' => 2, 'is_open' => false],
            ['id' => 13, 'name' => 'Agrupar Siguiente Visita', 'color' => '#edeae5', 'lifecycle' => 2, 'is_open' => true],
            ['id' => 14, 'name' => 'Recibida - OK por Organizar', 'color' => '#f6eac2', 'lifecycle' => 2, 'is_open' => true],
            ['id' => 15, 'name' => 'Pend.Info Establecimiento', 'color' => '#ffdac1', 'lifecycle' => 3, 'is_open' => true],
            ['id' => 16, 'name' => 'En Espera de Material', 'color' => '#ffdac1', 'lifecycle' => 3, 'is_open' => true],
            ['id' => 17, 'name' => 'En Espera del Cliente', 'color' => '#ffdac1', 'lifecycle' => 3, 'is_open' => true],
            ['id' => 18, 'name' => 'En Progreso', 'color' => '#a9cef0', 'lifecycle' => 3, 'is_open' => true],
            ['id' => 19, 'name' => 'Finalizada - Cliente No informado', 'color' => '#97c1a9', 'lifecycle' => 4, 'is_open' => true],
            ['id' => 20, 'name' => 'Finalizada', 'color' => '#97c1a9', 'lifecycle' => 5, 'is_open' => false],
            ['id' => 21, 'name' => 'Esperando Manager', 'color' => '#dfccf1', 'lifecycle' => 6, 'is_open' => false],
            ['id' => 22, 'name' => 'OK Controllers', 'color' => '#dfccf1', 'lifecycle' => 6, 'is_open' => false],
            ['id' => 23, 'name' => 'PO Reclamada', 'color' => '#dfccf1', 'lifecycle' => 7, 'is_open' => false],
            ['id' => 24, 'name' => 'Aceptada', 'color' => '#c7dbda', 'lifecycle' => 7, 'is_open' => false],
            ['id' => 183, 'name' => 'Aceptada No Facturable', 'color' => '#c7dbda', 'lifecycle' => 7, 'is_open' => false],
            ['id' => 25, 'name' => 'Facturada', 'color' => '#c7dbda', 'lifecycle' => 8, 'is_open' => false],
            ['id' => 132, 'name' => 'Abonada para Refacturar', 'color' => '#c7dbda', 'lifecycle' => 7, 'is_open' => false],
        ];

        foreach ($statuses as $status) {
            WorkOrderStatus::query()->updateOrCreate(
                ['id' => $status['id']],
                [
                    'name' => $status['name'],
                    'color' => $status['color'],
                    'lifecycle' => $status['lifecycle'],
                    'is_open' => $status['is_open'],
                ],
            );
        }
    }
}
