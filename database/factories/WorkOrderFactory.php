<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\WorkOrders\Enums\WorkOrderStage;
use App\Models\Establishment;
use App\Models\WorkOrder;
use App\Models\WorkOrderStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrder>
 */
class WorkOrderFactory extends Factory
{
    protected $model = WorkOrder::class;

    public function configure(): static
    {
        return $this->afterMaking(function (WorkOrder $workOrder): void {
            $stage = $workOrder->stage instanceof WorkOrderStage
                ? $workOrder->stage
                : WorkOrderStage::from((string) $workOrder->stage);

            if ($stage === WorkOrderStage::Estimate) {
                $workOrder->is_estimate = true;
                $workOrder->is_work_order = (bool) $workOrder->is_work_order;
                $workOrder->estimate_num = $workOrder->code ?: $workOrder->estimate_num;

                return;
            }

            $workOrder->is_work_order = true;
            $workOrder->is_estimate = (bool) $workOrder->is_estimate;
            $workOrder->work_order_num = $workOrder->code ?: $workOrder->work_order_num;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = strtoupper(fake()->unique()->bothify('EST-#####'));

        return [
            'code' => $code,
            'is_estimate' => true,
            'is_work_order' => false,
            'estimate_num' => $code,
            'estimate_num_cardinal' => null,
            'estimate_numbering_pattern_id' => null,
            'estimate_old_num' => null,
            'work_order_num' => null,
            'work_order_num_cardinal' => null,
            'work_order_numbering_pattern_id' => null,
            'work_order_old_num' => null,
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
            'owner_company_id' => null,
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

            $code = strtoupper(fake()->unique()->bothify('WO-#####'));

            return [
                'stage' => WorkOrderStage::WorkOrder,
                'status_id' => $status->id,
                'confirmed_at' => now(),
                'code' => $code,
                'is_estimate' => false,
                'is_work_order' => true,
                'estimate_num' => null,
                'estimate_num_cardinal' => null,
                'estimate_numbering_pattern_id' => null,
                'estimate_old_num' => null,
                'work_order_num' => $code,
                'work_order_num_cardinal' => null,
                'work_order_numbering_pattern_id' => null,
                'work_order_old_num' => null,
            ];
        });
    }

    /**
     * Confirmed estimate → work order (both identity flags true, both codes).
     */
    public function confirmedFromEstimate(?string $estimateNum = null, ?string $workOrderNum = null): static
    {
        return $this->state(function () use ($estimateNum, $workOrderNum): array {
            $status = WorkOrderStatus::query()->firstOrCreate(
                ['name' => 'Recibida - OK por Organizar', 'kind' => WorkOrderStage::WorkOrder],
                [
                    'color' => '#f6eac2',
                    'lifecycle' => 2,
                    'is_open' => true,
                ],
            );

            $estimateCode = $estimateNum ?? strtoupper(fake()->unique()->bothify('EST-#####'));
            $woCode = $workOrderNum ?? strtoupper(fake()->unique()->bothify('WO-#####'));

            return [
                'stage' => WorkOrderStage::WorkOrder,
                'status_id' => $status->id,
                'confirmed_at' => now(),
                'code' => $woCode,
                'is_estimate' => true,
                'is_work_order' => true,
                'estimate_num' => $estimateCode,
                'work_order_num' => $woCode,
            ];
        });
    }
}
