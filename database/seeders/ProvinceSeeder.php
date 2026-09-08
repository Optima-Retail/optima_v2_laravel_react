<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Province;
use Illuminate\Database\Seeder;
use RuntimeException;

final class ProvinceSeeder extends Seeder
{
    public function run(): void
    {
        $country = Country::query()
            ->where(function ($query): void {
                $query->where('iso_code', 'ES')
                    ->orWhere('name', 'España');
            })
            ->first();

        if ($country === null) {
            throw new RuntimeException('Spain country (iso_code ES / name España) not found. Seed countries first.');
        }

        $path = database_path('data/spain_provinces.php');

        if (! is_readable($path)) {
            throw new RuntimeException("Province data file not found or unreadable: {$path}");
        }

        /** @var list<array{code: string, name: string}> $rows */
        $rows = require $path;

        foreach ($rows as $row) {
            Province::query()->updateOrCreate(
                [
                    'country_id' => $country->id,
                    'code' => $row['code'],
                ],
                [
                    'name' => $row['name'],
                ],
            );
        }
    }
}
