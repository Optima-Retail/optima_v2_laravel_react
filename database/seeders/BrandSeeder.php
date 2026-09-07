<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Company;
use Illuminate\Database\Seeder;

final class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $or = Company::query()->where('tax_id', 'B66409087')->first();
        $oril = Company::query()->where('tax_id', 'IE4115369FH')->first();

        $brands = [
            [
                'name' => 'ZARA',
                'corporation_company_id' => $or?->id,
            ],
            [
                'name' => 'NIKE',
                'corporation_company_id' => $oril?->id,
            ],
        ];

        foreach ($brands as $attributes) {
            Brand::query()->updateOrCreate(
                ['name' => $attributes['name']],
                $attributes + [
                    'is_quality_control_contactable' => true,
                    'send_debt_reminders' => true,
                ],
            );
        }
    }
}
