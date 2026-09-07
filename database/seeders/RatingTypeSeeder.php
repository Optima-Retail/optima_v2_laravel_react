<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\RatingType;
use Illuminate\Database\Seeder;

/**
 * Legacy TipoValoracionEnum catalog (optimaback tipos_valoracion).
 */
final class RatingTypeSeeder extends Seeder
{
    public function run(): void
    {
        $ratingTypes = [
            [
                'id' => 1,
                'name' => 'Estrellas',
                'code' => 'stars',
                'max_score' => 5,
            ],
            [
                'id' => 2,
                'name' => 'Caras',
                'code' => 'faces',
                'max_score' => 10,
            ],
        ];

        foreach ($ratingTypes as $ratingType) {
            RatingType::query()->updateOrCreate(
                ['id' => $ratingType['id']],
                [
                    'name' => $ratingType['name'],
                    'code' => $ratingType['code'],
                    'max_score' => $ratingType['max_score'],
                ],
            );
        }
    }
}
