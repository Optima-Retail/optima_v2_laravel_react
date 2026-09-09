<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\EvaluationStatus;
use Illuminate\Database\Seeder;

/**
 * Evaluation statuses from optimaback EstadosEvaluacionesEnum (estados where modelo = Evaluacion).
 * Legacy IDs preserved for future data migration. Colors are optional (not in legacy enum metadata).
 */
final class EvaluationStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['id' => 73, 'name' => 'Abierta', 'color' => '#f6eac2', 'lifecycle' => 1, 'is_open' => true],
            ['id' => 74, 'name' => 'Espera Tienda', 'color' => '#ffdac1', 'lifecycle' => 2, 'is_open' => true],
            ['id' => 75, 'name' => 'Pendiente Respuesta Tienda', 'color' => '#a9cef0', 'lifecycle' => 3, 'is_open' => true],
            ['id' => 76, 'name' => 'Revisión QC', 'color' => '#c7dbda', 'lifecycle' => 4, 'is_open' => true],
            ['id' => 99, 'name' => 'Finalizada', 'color' => '#c5e1a5', 'lifecycle' => 5, 'is_open' => false],
            ['id' => 117, 'name' => 'Contacto Fallido Tienda', 'color' => '#ffcdd2', 'lifecycle' => 2, 'is_open' => false],
            ['id' => 133, 'name' => 'Contacto Fallido QC', 'color' => '#ef9a9a', 'lifecycle' => 2, 'is_open' => false],
            ['id' => 134, 'name' => 'No Contactable', 'color' => '#edeae5', 'lifecycle' => 1, 'is_open' => false],
            ['id' => 139, 'name' => 'Anulada', 'color' => '#bdbdbd', 'lifecycle' => 2, 'is_open' => false],
        ];

        foreach ($statuses as $status) {
            EvaluationStatus::query()->updateOrCreate(
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
