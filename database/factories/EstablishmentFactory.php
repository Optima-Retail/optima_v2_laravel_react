<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use App\Models\Establishment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Establishment>
 */
class EstablishmentFactory extends Factory
{
    protected $model = Establishment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->company().' '.$this->faker->city(),
            'code' => strtoupper(fake()->unique()->bothify('EST-###')),
            'city' => fake()->city(),
            'is_active' => true,
        ];
    }
}
