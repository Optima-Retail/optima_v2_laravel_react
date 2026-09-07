<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Seeder;

/**
 * Optima equipos catalog (EquipoEnum nombres), same labels as optimaback.
 * manager_id and controller_id are left empty intentionally.
 */
final class TeamSeeder extends Seeder
{
    public function run(): void
    {
        $teams = [
            ['code' => 'ADM', 'name' => 'Administración'],
            ['code' => 'COM', 'name' => 'Comercial'],
            ['code' => 'EQ01', 'name' => 'Equipo 1'],
            ['code' => 'EQ03', 'name' => 'Equipo 3'],
            ['code' => 'EQ04', 'name' => 'Equipo 4'],
            ['code' => 'EQ05', 'name' => 'Equipo 5'],
            ['code' => 'EQ06', 'name' => 'Equipo 6'],
            ['code' => 'EQ07', 'name' => 'Equipo 7'],
            ['code' => 'EQ08', 'name' => 'Equipo 8'],
            ['code' => 'EQ09', 'name' => 'Equipo 9'],
            ['code' => 'EQ10', 'name' => 'Equipo 10'],
            ['code' => 'EQ11', 'name' => 'Equipo 11'],
            ['code' => 'FIN', 'name' => 'Finanzas'],
            ['code' => 'FORM', 'name' => 'Formación'],
            ['code' => 'GUA', 'name' => 'Guardia'],
            ['code' => 'IT', 'name' => 'Departamento de IT'],
            ['code' => 'PRL', 'name' => 'Prevención de riesgos laborales'],
            ['code' => 'QC', 'name' => 'Quality'],
            ['code' => 'REC', 'name' => 'Recepción'],
            ['code' => 'TEC', 'name' => 'Técnicos'],
            ['code' => 'EQ00', 'name' => 'Equipo 0 (bajas)'],
            ['code' => 'CON', 'name' => 'Controller'],
            ['code' => 'INC', 'name' => 'Controllers Incidencias'],
            ['code' => 'EQ20', 'name' => 'Equipo 20'],
        ];

        foreach ($teams as $team) {
            Team::query()->updateOrCreate(
                ['code' => $team['code']],
                [
                    'name' => $team['name'],
                    'manager_id' => null,
                    'controller_id' => null,
                ],
            );
        }
    }
}
