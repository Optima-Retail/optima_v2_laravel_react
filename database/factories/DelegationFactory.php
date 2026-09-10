<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Delegation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Delegation>
 */
class DelegationFactory extends Factory
{
    protected $model = Delegation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->city().' ('.fake()->currencyCode().')',
            'tax_id' => ($taxId = fake()->optional()->bothify('??########')) !== null
                ? strtoupper($taxId)
                : null,
            'address' => fake()->optional()->streetAddress(),
            'cost_includes_vat' => false,
            'recovers_vat' => true,
            'billing_info' => null,
        ];
    }
}
