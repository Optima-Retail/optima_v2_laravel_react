<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\TechnicianAttendanceConfirmationType;
use Illuminate\Database\Seeder;

/**
 * Legacy TecnicoTipoConfirmacionAsistenciaEnum catalog.
 * IDs preserved: 1 Whatsapp, 2 Llamada, 3 No contestado.
 */
final class TechnicianAttendanceConfirmationTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['id' => 1, 'name' => 'Whatsapp'],
            ['id' => 2, 'name' => 'Llamada'],
            ['id' => 3, 'name' => 'No contestado'],
        ];

        foreach ($types as $type) {
            TechnicianAttendanceConfirmationType::query()->updateOrCreate(
                ['id' => $type['id']],
                ['name' => $type['name']],
            );
        }
    }
}
