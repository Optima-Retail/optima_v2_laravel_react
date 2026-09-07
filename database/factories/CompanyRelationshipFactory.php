<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Companies\Enums\CompanyRelationshipClassification;
use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\Companies\Enums\CompanyRelationshipStatus;
use App\Models\Company;
use App\Models\CompanyRelationship;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyRelationship>
 */
class CompanyRelationshipFactory extends Factory
{
    protected $model = CompanyRelationship::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_company_id' => Company::factory(),
            'related_company_id' => Company::factory(),
            'kind' => CompanyRelationshipKind::Customer,
            'status' => CompanyRelationshipStatus::Active,
            'classification' => CompanyRelationshipClassification::Commercial,
        ];
    }
}
