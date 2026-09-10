<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\JobTitle;
use Illuminate\Database\Seeder;

/**
 * Optima job titles catalog (CargoEnum / cargos).
 * Legacy IDs preserved; id 15 was skipped in legacy.
 */
final class JobTitleSeeder extends Seeder
{
    public function run(): void
    {
        $jobTitles = [
            ['id' => 1, 'name' => 'Store Manager', 'code' => 'SM'],
            ['id' => 2, 'name' => 'Area Manager', 'code' => 'AM'],
            ['id' => 3, 'name' => 'Global Area Manager', 'code' => 'GAM'],
            ['id' => 4, 'name' => 'Assistant', 'code' => 'ASS'],
            ['id' => 5, 'name' => 'Administración', 'code' => 'ADM'],
            ['id' => 6, 'name' => 'Comercial', 'code' => 'COM'],
            ['id' => 7, 'name' => 'District Manager', 'code' => 'DM'],
            ['id' => 8, 'name' => 'Regional Manager', 'code' => 'RM'],
            ['id' => 9, 'name' => 'Técnico', 'code' => 'TEC'],
            ['id' => 10, 'name' => 'Finanzas', 'code' => 'FIN'],
            ['id' => 11, 'name' => 'Agendar Fechas', 'code' => 'CAL'],
            ['id' => 12, 'name' => 'Jefe de Técnicos', 'code' => 'TB'],
            ['id' => 13, 'name' => 'Staff Tienda', 'code' => 'STF'],
            ['id' => 14, 'name' => 'Facility Manager', 'code' => 'FM'],
            ['id' => 16, 'name' => 'Administración Facturas', 'code' => 'AF'],
            ['id' => 17, 'name' => 'Admin. Reclamación', 'code' => 'AR'],
        ];

        foreach ($jobTitles as $jobTitle) {
            JobTitle::query()->updateOrCreate(
                ['id' => $jobTitle['id']],
                [
                    'name' => $jobTitle['name'],
                    'code' => $jobTitle['code'],
                ],
            );
        }
    }
}
