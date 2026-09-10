<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

/** Legacy FormasPagoEnum IDs preserved. */
final class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            ['id' => 31, 'name' => 'Immediato', 'due_count' => 1, 'days' => 1, 'code' => null],
            ['id' => 32, 'name' => '15 dias', 'due_count' => 1, 'days' => 15, 'code' => null],
            ['id' => 33, 'name' => '30 dias', 'due_count' => 1, 'days' => 30, 'code' => null],
            ['id' => 34, 'name' => '60 dias', 'due_count' => 1, 'days' => 60, 'code' => null],
            ['id' => 35, 'name' => '90 dias', 'due_count' => 1, 'days' => 90, 'code' => null],
            ['id' => 36, 'name' => '180 dias', 'due_count' => 1, 'days' => 180, 'code' => null],
            ['id' => 37, 'name' => '45 dias', 'due_count' => 1, 'days' => 45, 'code' => null],
        ];

        foreach ($methods as $method) {
            PaymentMethod::query()->updateOrCreate(
                ['id' => $method['id']],
                [
                    'name' => $method['name'],
                    'due_count' => $method['due_count'],
                    'days' => $method['days'],
                    'code' => $method['code'],
                    'is_active' => true,
                ],
            );
        }
    }
}
