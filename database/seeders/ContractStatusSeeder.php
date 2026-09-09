<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ContractStatus;
use Illuminate\Database\Seeder;

/**
 * Contract statuses from optimaback EstadosContratoEnum (estados where modelo = Contrato).
 * Legacy IDs preserved for future data migration. Colors are optional (not in legacy enum metadata).
 */
final class ContractStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['id' => 77, 'name' => 'Abierto', 'color' => '#f6eac2', 'lifecycle' => 1, 'is_open' => true],
            ['id' => 95, 'name' => 'Generando', 'color' => '#ffdac1', 'lifecycle' => 2, 'is_open' => true],
            ['id' => 78, 'name' => 'En Curso', 'color' => '#a9cef0', 'lifecycle' => 3, 'is_open' => true],
            ['id' => 79, 'name' => 'Renovado', 'color' => '#c7dbda', 'lifecycle' => 4, 'is_open' => false],
            ['id' => 80, 'name' => 'Cancelado', 'color' => '#edeae5', 'lifecycle' => 4, 'is_open' => false],
        ];

        foreach ($statuses as $status) {
            ContractStatus::query()->updateOrCreate(
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
