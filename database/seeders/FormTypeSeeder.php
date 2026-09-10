<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\FormType;
use Illuminate\Database\Seeder;

/**
 * Legacy TipoFormularioEnum IDs 1–6 (`formularios_tipos`).
 */
final class FormTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['id' => 1, 'name' => 'Correctivo'],
            ['id' => 2, 'name' => 'Preventivo'],
            ['id' => 3, 'name' => 'QC'],
            ['id' => 4, 'name' => 'Tecnico'],
            ['id' => 5, 'name' => 'Subformulario'],
            ['id' => 6, 'name' => 'Establecimiento'],
        ];

        foreach ($types as $type) {
            FormType::query()->updateOrCreate(
                ['id' => $type['id']],
                ['name' => $type['name']],
            );
        }
    }
}
