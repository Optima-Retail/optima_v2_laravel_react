<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\TechnicianRequests\Enums\TechnicianRequestPriorityKey;
use App\Models\TechnicianRequestPriority;
use Illuminate\Database\Seeder;

/**
 * Technician request priorities from legacy `tecnicos_prioridades`.
 * Legacy IDs 1–4 preserved. Due dates are computed from `key` in the domain service.
 */
final class TechnicianRequestPrioritySeeder extends Seeder
{
    public function run(): void
    {
        $priorities = [
            [
                'id' => 1,
                'name' => 'Urgencia',
                'key' => TechnicianRequestPriorityKey::Urgent->value,
                'color' => '#FF0000',
            ],
            [
                'id' => 2,
                'name' => 'Prioridad alta',
                'key' => TechnicianRequestPriorityKey::High->value,
                'color' => '#FF8000',
            ],
            [
                'id' => 3,
                'name' => 'Prioridad media',
                'key' => TechnicianRequestPriorityKey::Medium->value,
                'color' => '#FFFF00',
            ],
            [
                'id' => 4,
                'name' => 'Prioridad baja',
                'key' => TechnicianRequestPriorityKey::Low->value,
                'color' => '#00FF00',
            ],
        ];

        foreach ($priorities as $priority) {
            $model = TechnicianRequestPriority::withTrashed()->find($priority['id'])
                ?? new TechnicianRequestPriority;

            $model->forceFill($priority)->save();
        }
    }
}
