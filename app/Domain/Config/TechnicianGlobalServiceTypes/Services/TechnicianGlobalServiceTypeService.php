<?php

declare(strict_types=1);

namespace App\Domain\Config\TechnicianGlobalServiceTypes\Services;

use App\Models\CompanyRelationship;
use App\Models\GlobalServiceType;
use App\Models\TechnicianGlobalServiceType;
use Illuminate\Support\Facades\DB;

final class TechnicianGlobalServiceTypeService
{
    /**
     * @return list<array{id: int, global_service_type_id: int, global_service_type_label: string, global_service_type_color: string|null}>
     */
    public function forTechnician(CompanyRelationship $relationship): array
    {
        return TechnicianGlobalServiceType::query()
            ->with('globalServiceType:id,name,code,color')
            ->where('company_relationship_id', $relationship->id)
            ->orderBy('global_service_type_id')
            ->get()
            ->map(function (TechnicianGlobalServiceType $row): array {
                $globalServiceType = $row->globalServiceType;

                return [
                    'id' => $row->id,
                    'global_service_type_id' => $row->global_service_type_id,
                    'global_service_type_label' => $globalServiceType
                        ? ($globalServiceType->code
                            ? "{$globalServiceType->code} — {$globalServiceType->name}"
                            : $globalServiceType->name)
                        : '',
                    'global_service_type_color' => $globalServiceType?->color,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Replace the technician's global service types with the given ids.
     *
     * @param  list<int>  $globalServiceTypeIds
     * @return list<array{id: int, global_service_type_id: int, global_service_type_label: string, global_service_type_color: string|null}>
     */
    public function syncForTechnician(CompanyRelationship $relationship, array $globalServiceTypeIds): array
    {
        return DB::transaction(function () use ($relationship, $globalServiceTypeIds): array {
            $wanted = [];
            foreach ($globalServiceTypeIds as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $wanted[$id] = $id;
                }
            }
            $wantedIds = array_values($wanted);

            $existing = TechnicianGlobalServiceType::withTrashed()
                ->where('company_relationship_id', $relationship->id)
                ->get();

            foreach ($existing as $row) {
                $rowGlobalServiceTypeId = (int) $row->global_service_type_id;

                if (! in_array($rowGlobalServiceTypeId, $wantedIds, true)) {
                    if (! $row->trashed()) {
                        $row->delete();
                    }

                    continue;
                }

                if ($row->trashed()) {
                    $row->restore();
                }
            }

            $existingIds = $existing->pluck('global_service_type_id')->map(fn ($id): int => (int) $id)->all();

            foreach ($wantedIds as $globalServiceTypeId) {
                if (in_array($globalServiceTypeId, $existingIds, true)) {
                    continue;
                }

                TechnicianGlobalServiceType::query()->create([
                    'company_relationship_id' => $relationship->id,
                    'global_service_type_id' => $globalServiceTypeId,
                ]);
            }

            return $this->forTechnician($relationship);
        });
    }

    /**
     * @return list<array{id: int, label: string, color: string|null}>
     */
    public function globalServiceTypeOptions(): array
    {
        return GlobalServiceType::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'color'])
            ->map(fn (GlobalServiceType $type): array => [
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
