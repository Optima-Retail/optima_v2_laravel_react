<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use App\Models\Requester;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Requester>
 */
class RequesterFactory extends Factory
{
    protected $model = Requester::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->name(),
            'emails' => [fake()->safeEmail()],
        ];
    }
}
