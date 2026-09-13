<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\TechnicianIncidentType;
use Illuminate\Database\Seeder;

/**
 * Technician incident types from optimaback TecnicosIncidenciasTiposEnum.
 * Legacy IDs preserved for future data migration.
 */
final class TechnicianIncidentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['id' => 1, 'name' => 'Feedback', 'due_days' => 1, 'send_mail_to_technician' => false],
            ['id' => 2, 'name' => 'Negociación', 'due_days' => 5, 'send_mail_to_technician' => false],
            ['id' => 3, 'name' => 'Reclamación', 'due_days' => 1, 'send_mail_to_technician' => true],
        ];

        foreach ($types as $type) {
            $model = TechnicianIncidentType::withTrashed()->find($type['id'])
                ?? new TechnicianIncidentType;

            $model->forceFill($type)->save();
        }
    }
}
