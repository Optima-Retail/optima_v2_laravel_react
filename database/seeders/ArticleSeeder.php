<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Article;
use Illuminate\Database\Seeder;

/**
 * System articles from optimaback ArticuloEnum.
 * Spanish language_id=1; all seeded rows are non-deletable.
 */
final class ArticleSeeder extends Seeder
{
    public function run(): void
    {
        $articles = [
            [
                'code' => '0',
                'name' => 'No Name',
                'description' => 'No Name',
            ],
            [
                'code' => 'CAPITULO',
                'name' => 'CAPITULO',
                'description' => 'Capitulo',
            ],
            [
                'code' => 'DL',
                'name' => 'Desplazamiento Laboral',
                'description' => 'Desplazamiento Laboral',
            ],
            [
                'code' => 'DEL',
                'name' => 'Desplazamiento Extra Laboral',
                'description' => 'Desplazamiento Extra Laboral',
            ],
            [
                'code' => 'ML',
                'name' => 'Mano de Obra Laboral',
                'description' => 'Mano de Obra Laboral',
            ],
            [
                'code' => 'MEL',
                'name' => 'Mano de Obra Extra Laboral',
                'description' => 'Mano de Obra Extra Laboral',
            ],
            [
                'code' => 'MAT',
                'name' => 'Material',
                'description' => 'Material',
            ],
        ];

        foreach ($articles as $row) {
            $article = Article::query()->updateOrCreate(
                ['code' => $row['code']],
                ['is_deletable' => false],
            );

            $article->languages()->updateOrCreate(
                ['language_id' => 1],
                [
                    'name' => $row['name'],
                    'description' => $row['description'],
                ],
            );
        }
    }
}
