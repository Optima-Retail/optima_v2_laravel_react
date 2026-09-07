<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Delegation;
use App\Models\Series;
use Illuminate\Database\Seeder;

final class DelegationSeeder extends Seeder
{
    public function run(): void
    {
        $or = Company::query()->where('tax_id', 'B66409087')->first();
        $oril = Company::query()->where('tax_id', 'IE4115369FH')->first();
        $eur = Currency::query()->where('code', 'EUR')->first();
        $spain = Country::query()->where('iso_code', 'ES')->orWhere('name', 'Spain')->first();
        $ireland = Country::query()->where('iso_code', 'IE')->orWhere('name', 'Ireland')->first();
        $series = Series::query()->orderBy('id')->first();

        $rows = [
            [
                'id' => 1,
                'name' => 'OR-Principal (EUR)',
                'tax_id' => 'ESB66409087',
                'company_id' => $or?->id,
                'currency_id' => $eur?->id,
                'country_id' => $spain?->id,
                'series_id' => $series?->id,
                'cost_includes_vat' => false,
                'recovers_vat' => true,
                'billing_info' => [
                    'CIF' => 'B-66409087',
                    'VAT INTRA' => 'ESB66409087',
                ],
            ],
            [
                'id' => 61,
                'name' => 'ORIL-Principal (EUR)',
                'tax_id' => 'IE4115369FH',
                'company_id' => $oril?->id,
                'currency_id' => $eur?->id,
                'country_id' => $ireland?->id ?? $spain?->id,
                'series_id' => $series?->id,
                'cost_includes_vat' => false,
                'recovers_vat' => true,
                'billing_info' => [
                    'VAT' => 'IE4115369FH',
                ],
            ],
        ];

        foreach ($rows as $row) {
            Delegation::query()->updateOrCreate(
                ['id' => $row['id']],
                $row,
            );
        }
    }
}
