<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\OtherExpenseType;
use Illuminate\Database\Seeder;

/**
 * Other expense types from optimaback OtherExpenseTypeEnum.
 * Legacy IDs preserved for future movements migration.
 */
final class OtherExpenseTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['id' => 1, 'name' => 'Indemnizaciones extraordinarias por despido'],
            ['id' => 2, 'name' => 'Costes legales puntuales'],
            ['id' => 3, 'name' => 'Gastos por eventos no repetibles'],
            ['id' => 4, 'name' => 'Salario fuera mercado socios'],
            ['id' => 5, 'name' => 'Empleados no de Optima (Empleada hogar - Empleado MTA)'],
            ['id' => 6, 'name' => 'CAPEX tecnológico desarrolladores internos (el 80% del coste del dept) el 20% es para mantenimiento'],
            ['id' => 7, 'name' => 'Otros gastos de Socios'],
            ['id' => 8, 'name' => 'Costes financieros'],
            ['id' => 9, 'name' => 'Amortizaciones'],
        ];

        foreach ($types as $type) {
            OtherExpenseType::query()->updateOrCreate(
                ['id' => $type['id']],
                ['name' => $type['name']],
            );
        }
    }
}
