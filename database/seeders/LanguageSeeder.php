<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Seeder;

/**
 * Optima idiomas catalog (IdiomasEnum).
 * Uses `codigo` only — `iso_639_1` was redundant in optimaback and is omitted.
 */
final class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        $languages = [
            ['id' => 1, 'name' => 'ESPAÑOL', 'code' => 'es'],
            ['id' => 2, 'name' => 'INGLÉS', 'code' => 'en'],
            ['id' => 3, 'name' => 'FRANCÉS', 'code' => 'fr'],
            ['id' => 4, 'name' => 'ITALIANO', 'code' => 'it'],
            ['id' => 5, 'name' => 'ALEMÁN', 'code' => 'de'],
            ['id' => 6, 'name' => 'PORTUGUÉS', 'code' => 'pt'],
            ['id' => 7, 'name' => 'POLACO', 'code' => 'pl'],
            ['id' => 8, 'name' => 'TURCO', 'code' => 'tr'],
            ['id' => 9, 'name' => 'GRIEGO', 'code' => 'el'],
            ['id' => 10, 'name' => 'SERBIO', 'code' => 'rs'],
            ['id' => 11, 'name' => 'CHECO', 'code' => 'cs'],
            ['id' => 12, 'name' => 'CATALÁN', 'code' => 'ca'],
            ['id' => 13, 'name' => 'LITUANO', 'code' => 'lt'],
            ['id' => 14, 'name' => 'RUMANO', 'code' => 'ro'],
            ['id' => 15, 'name' => 'HUNGARO', 'code' => 'hu'],
            ['id' => 16, 'name' => 'CROATA', 'code' => 'hr'],
            ['id' => 17, 'name' => 'LETON', 'code' => 'lv'],
            ['id' => 18, 'name' => 'NORUEGO', 'code' => 'no'],
            ['id' => 19, 'name' => 'DANÉS', 'code' => 'da'],
            ['id' => 20, 'name' => 'FINÉS', 'code' => 'fi'],
            ['id' => 21, 'name' => 'SUECO', 'code' => 'sv'],
        ];

        foreach ($languages as $language) {
            Language::query()->updateOrCreate(
                ['id' => $language['id']],
                [
                    'name' => $language['name'],
                    'code' => $language['code'],
                ],
            );
        }
    }
}
