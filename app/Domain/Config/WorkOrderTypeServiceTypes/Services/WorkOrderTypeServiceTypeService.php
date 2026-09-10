<?php

declare(strict_types=1);

namespace App\Domain\Config\WorkOrderTypeServiceTypes\Services;

use App\Models\ServiceType;
use App\Models\WorkOrderType;
use App\Models\WorkOrderTypeServiceType;
use Illuminate\Support\Facades\DB;

final class WorkOrderTypeServiceTypeService
{
    /**
     * @return list<array{id: int, service_type_id: int, service_type_label: string, service_type_color: string|null}>
     */
    public function forWorkOrderType(WorkOrderType $workOrderType): array
    {
        return WorkOrderTypeServiceType::query()
            ->with('serviceType:id,name,code,color')
            ->where('work_order_type_id', $workOrderType->id)
            ->orderBy('service_type_id')
            ->get()
            ->map(function (WorkOrderTypeServiceType $row): array {
                $serviceType = $row->serviceType;

                return [
                    'id' => $row->id,
                    'service_type_id' => $row->service_type_id,
                    'service_type_label' => $serviceType
                        ? ($serviceType->code
                            ? "{$serviceType->code} — {$serviceType->name}"
                            : $serviceType->name)
                        : '',
                    'service_type_color' => $serviceType?->color,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $serviceTypeIds
     * @return list<array{id: int, service_type_id: int, service_type_label: string, service_type_color: string|null}>
     */
    public function syncForWorkOrderType(WorkOrderType $workOrderType, array $serviceTypeIds): array
    {
        return DB::transaction(function () use ($workOrderType, $serviceTypeIds): array {
            $wanted = [];
            foreach ($serviceTypeIds as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $wanted[$id] = $id;
                }
            }
            $wantedIds = array_values($wanted);

            $existing = WorkOrderTypeServiceType::withTrashed()
                ->where('work_order_type_id', $workOrderType->id)
                ->get();

            foreach ($existing as $row) {
                $rowServiceTypeId = (int) $row->service_type_id;

                if (! in_array($rowServiceTypeId, $wantedIds, true)) {
                    if (! $row->trashed()) {
                        $row->delete();
                    }

                    continue;
                }

                if ($row->trashed()) {
                    $row->restore();
                }
            }

            $existingIds = $existing->pluck('service_type_id')->map(fn ($id): int => (int) $id)->all();

            foreach ($wantedIds as $serviceTypeId) {
                if (in_array($serviceTypeId, $existingIds, true)) {
                    continue;
                }

                WorkOrderTypeServiceType::query()->create([
                    'work_order_type_id' => $workOrderType->id,
                    'service_type_id' => $serviceTypeId,
                ]);
            }

            return $this->forWorkOrderType($workOrderType);
        });
    }

    /**
     * @return list<array{id: int, label: string, color: string|null}>
     */
    public function serviceTypeOptions(): array
    {
        return ServiceType::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'color'])
            ->map(fn (ServiceType $type): array => [
                'id' => $type->id,
                'label' => $type->code
                    ? "{$type->code} — {$type->name}"
                    : $type->name,
                'color' => $type->color,
            ])
            ->values()
            ->all();
    }
}
