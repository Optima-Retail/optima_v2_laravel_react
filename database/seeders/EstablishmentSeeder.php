<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Companies\Enums\CompanyKind;
use App\Domain\Companies\Enums\CompanyRelationshipClassification;
use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\Companies\Enums\CompanyRelationshipStatus;
use App\Models\Brand;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\Establishment;
use Illuminate\Database\Seeder;

final class EstablishmentSeeder extends Seeder
{
    public function run(): void
    {
        $or = Company::query()->where('tax_id', 'B66409087')->first();

        if ($or === null) {
            return;
        }

        $rows = [
            [
                'client' => [
                    'name' => 'Zara Retail Spain',
                    'tradename' => 'Zara',
                    'slug' => 'zara-retail-spain',
                    'tax_id' => 'B87654321',
                ],
                'brand' => 'ZARA',
                'site' => [
                    'name' => 'Zara Passeig de Gràcia',
                    'code' => 'ZARA-BCN-01',
                    'city' => 'Barcelona',
                ],
            ],
            [
                'client' => [
                    'name' => 'Nike European Retail',
                    'tradename' => 'Nike',
                    'slug' => 'nike-european-retail',
                    'tax_id' => 'IE9988776A',
                ],
                'brand' => 'NIKE',
                'site' => [
                    'name' => 'Nike Town Dublin',
                    'code' => 'NIKE-DUB-01',
                    'city' => 'Dublin',
                ],
            ],
        ];

        foreach ($rows as $row) {
            $brand = Brand::query()->where('name', $row['brand'])->first();

            $client = Company::query()->updateOrCreate(
                ['tax_id' => $row['client']['tax_id']],
                $row['client'] + [
                    'kind' => CompanyKind::Party,
                    'is_active' => true,
                ],
            );

            CompanyRelationship::query()->updateOrCreate(
                [
                    'owner_company_id' => $or->id,
                    'related_company_id' => $client->id,
                    'kind' => CompanyRelationshipKind::Customer,
                    'deleted_token' => '',
                ],
                [
                    'status' => CompanyRelationshipStatus::Active,
                    'classification' => CompanyRelationshipClassification::Commercial,
                    'brand_id' => $brand?->id,
                ],
            );

            Establishment::query()->updateOrCreate(
                [
                    'company_id' => $client->id,
                    'code' => $row['site']['code'],
                ],
                [
                    'name' => $row['site']['name'],
                    'city' => $row['site']['city'],
                    'is_active' => true,
                ],
            );
        }
    }
}
