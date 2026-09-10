<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\FormStatus;
use Illuminate\Database\Seeder;

/**
 * Form statuses from optimaback EstadoFormularioEnum (`formularios_estados`).
 * Legacy IDs 1–4 preserved for future data migration.
 */
final class FormStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['id' => 1, 'name' => 'Creando', 'next_status_id' => 2],
            ['id' => 2, 'name' => 'Editando', 'next_status_id' => 3],
            ['id' => 3, 'name' => 'Revisando', 'next_status_id' => 4],
            ['id' => 4, 'name' => 'Terminado', 'next_status_id' => null],
        ];

        foreach ($statuses as $status) {
            FormStatus::query()->updateOrCreate(
                ['id' => $status['id']],
                [
                    'name' => $status['name'],
                    'is_active' => true,
                ],
            );
        }

        foreach ($statuses as $status) {
            FormStatus::query()->whereKey($status['id'])->update([
                'next_status_id' => $status['next_status_id'],
            ]);
        }
    }
}
