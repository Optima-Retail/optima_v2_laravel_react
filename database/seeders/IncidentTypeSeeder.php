<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\IncidentType;
use Illuminate\Database\Seeder;

/**
 * Catalog from legacy `tipos_incidencia` (IDs 10–16) with per-type form config.
 */
final class IncidentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'id' => 10,
                'name' => 'CX',
                'color' => '#FFFFFF',
                'default_priority_id' => 2,
                'origin_selectable' => true,
                'origin_options' => ['company', 'brand'],
                'default_origin_type' => 'company',
                'origin_required' => true,
                'related_type' => null,
                'show_related' => false,
            ],
            [
                'id' => 11,
                'name' => 'QC',
                'color' => '#FFFFFF',
                'default_priority_id' => 1,
                'origin_selectable' => false,
                'origin_options' => ['establishment'],
                'default_origin_type' => 'establishment',
                'origin_required' => true,
                'related_type' => 'evaluation',
                'show_related' => true,
            ],
            [
                'id' => 12,
                'name' => 'Administración',
                'color' => '#FFFFFF',
                'default_priority_id' => 2,
                'origin_selectable' => false,
                'origin_options' => [],
                'default_origin_type' => null,
                'origin_required' => false,
                'related_type' => null,
                'show_related' => false,
            ],
            [
                'id' => 13,
                'name' => 'Controllers',
                'color' => '#FFFFFF',
                'default_priority_id' => 2,
                'origin_selectable' => false,
                'origin_options' => [],
                'default_origin_type' => null,
                'origin_required' => false,
                'related_type' => null,
                'show_related' => false,
            ],
            [
                'id' => 14,
                'name' => 'Preventiva',
                'color' => '#FFFFFF',
                'default_priority_id' => 2,
                'origin_selectable' => true,
                'origin_options' => ['company', 'establishment', 'brand'],
                'default_origin_type' => 'establishment',
                'origin_required' => true,
                'related_type' => null,
                'show_related' => false,
            ],
            [
                'id' => 15,
                'name' => 'Predictiva',
                'color' => '#FFFFFF',
                'default_priority_id' => 2,
                'origin_selectable' => true,
                'origin_options' => ['company', 'establishment', 'brand'],
                'default_origin_type' => null,
                'origin_required' => true,
                'related_type' => null,
                'show_related' => false,
            ],
            [
                'id' => 16,
                'name' => 'Sales',
                'color' => '#FFFFFF',
                'default_priority_id' => 2,
                'origin_selectable' => true,
                'origin_options' => ['company', 'brand'],
                'default_origin_type' => null,
                'origin_required' => true,
                'related_type' => null,
                'show_related' => false,
            ],
        ];

        foreach ($types as $type) {
            IncidentType::query()->updateOrCreate(
                ['id' => $type['id']],
                [
                    'name' => $type['name'],
                    'color' => $type['color'],
                    'default_priority_id' => $type['default_priority_id'],
                    'origin_selectable' => $type['origin_selectable'],
                    'origin_options' => $type['origin_options'],
                    'default_origin_type' => $type['default_origin_type'],
                    'origin_required' => $type['origin_required'],
                    'related_type' => $type['related_type'],
                    'show_related' => $type['show_related'],
                ],
            );
        }
    }
}
