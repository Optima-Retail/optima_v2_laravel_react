<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\WorkOrders\Enums\WorkOrderStage;
use App\Models\Establishment;
use App\Models\WorkOrder;
use App\Models\WorkOrderStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WorkOrder>
 */
class WorkOrderFactory extends Factory
{
    protected $model = WorkOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => (string) Str::uuid(),
            'code' => strtoupper(fake()->unique()->bothify('EST-#####')),
            'subject' => fake()->sentence(6),
            'stage' => WorkOrderStage::Estimate,
            'status_id' => WorkOrderStatus::query()->firstOrCreate(
                ['name' => 'Pendiente', 'kind' => WorkOrderStage::Estimate],
                [
                    'color' => '#f6eac2',
                    'lifecycle' => 1,
                    'is_open' => true,
                ],
            )->id,
            'establishment_id' => Establishment::factory(),
        ];
    }

    public function workOrder(): static
    {
        return $this->state(function (): array {
            $status = WorkOrderStatus::query()->firstOrCreate(
                ['name' => 'Recibida - OK por Organizar', 'kind' => WorkOrderStage::WorkOrder],
                [
                    'color' => '#f6eac2',
                    'lifecycle' => 2,
                    'is_open' => true,
                ],
            );

            return [
                'stage' => WorkOrderStage::WorkOrder,
                'status_id' => $status->id,
                'confirmed_at' => now(),
                'code' => strtoupper(fake()->unique()->bothify('WO-#####')),
            ];
        });
    }
}
