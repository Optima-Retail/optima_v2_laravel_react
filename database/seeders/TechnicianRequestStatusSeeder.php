<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\TechnicianRequests\Enums\TechnicianRequestStatusKind;
use App\Models\TechnicianRequestStatus;
use Illuminate\Database\Seeder;

/**
 * Technician request statuses from legacy polymorphic `estados` (peticiones / filtraje).
 * Legacy IDs preserved for data migration.
 */
final class TechnicianRequestStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            [
                'id' => 38,
                'kind' => TechnicianRequestStatusKind::Request->value,
                'name' => 'Abierta',
                'color' => null,
                'lifecycle' => 1,
                'is_open' => true,
            ],
            [
                'id' => 39,
                'kind' => TechnicianRequestStatusKind::Request->value,
                'name' => 'En progreso',
                'color' => null,
                'lifecycle' => 2,
                'is_open' => true,
            ],
            [
                'id' => 40,
                'kind' => TechnicianRequestStatusKind::Request->value,
                'name' => 'Finalizada',
                'color' => null,
                'lifecycle' => 3,
                'is_open' => false,
            ],
            [
                'id' => 48,
                'kind' => TechnicianRequestStatusKind::Request->value,
                'name' => 'Cancelada',
                'color' => null,
                'lifecycle' => 4,
                'is_open' => false,
            ],
            [
                'id' => 59,
                'kind' => TechnicianRequestStatusKind::Screening->value,
                'name' => 'Abierto',
                'color' => null,
                'lifecycle' => 1,
                'is_open' => true,
            ],
            [
                'id' => 54,
                'kind' => TechnicianRequestStatusKind::Screening->value,
                'name' => 'Cita programada',
                'color' => null,
                'lifecycle' => 2,
                'is_open' => true,
            ],
            [
                'id' => 55,
                'kind' => TechnicianRequestStatusKind::Screening->value,
                'name' => 'Docu pendiente',
                'color' => null,
                'lifecycle' => 3,
                'is_open' => true,
            ],
            [
                'id' => 56,
                'kind' => TechnicianRequestStatusKind::Screening->value,
                'name' => 'Revision manager',
                'color' => null,
                'lifecycle' => 4,
                'is_open' => true,
            ],
            [
                'id' => 57,
                'kind' => TechnicianRequestStatusKind::Screening->value,
                'name' => 'No apto',
                'color' => null,
                'lifecycle' => 5,
                'is_open' => false,
            ],
            [
                'id' => 58,
                'kind' => TechnicianRequestStatusKind::Screening->value,
                'name' => 'Apto',
                'color' => null,
                'lifecycle' => 6,
                'is_open' => false,
            ],
        ];

        foreach ($statuses as $status) {
            $model = TechnicianRequestStatus::withTrashed()->find($status['id'])
                ?? new TechnicianRequestStatus;

            $model->forceFill($status)->save();
        }
    }
}
