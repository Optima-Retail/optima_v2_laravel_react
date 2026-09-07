<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Companies\Enums\CompanyKind;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'tradename' => fake()->optional()->catchPhrase(),
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'tax_id' => strtoupper(fake()->unique()->bothify('??########')),
            'kind' => CompanyKind::Party,
            'email' => fake()->optional()->companyEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'is_active' => true,
        ];
    }

    public function operating(): static
    {
        return $this->state(fn (): array => [
            'kind' => CompanyKind::OperatingCompany,
        ]);
    }
}
