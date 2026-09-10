<?php

declare(strict_types=1);

namespace App\Domain\Config\TechnicianServiceTypes\Services;

use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Models\CompanyRelationship;
use App\Models\ServiceType;
use App\Models\TechnicianServiceType;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class TechnicianServiceTypeService
{
    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, TechnicianServiceType>
     */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort(
            $filters,
            ['id', 'company_relationship_id', 'service_type_id', 'created_at'],
            'id',
        );

        return TechnicianServiceType::query()
            ->with([
                'companyRelationship.relatedCompany:id,name,tradename',
                'serviceType:id,name,code,color',
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner
                        ->whereHas('serviceType', function ($serviceType) use ($search): void {
                            $serviceType
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        })
                        ->orWhereHas('companyRelationship.relatedCompany', function ($company) use ($search): void {
                            $company
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('tradename', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateForWeb(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        return $this->paginate($filters, $perPage)
            ->through(fn (TechnicianServiceType $row): array => $this->toListItem($row));
    }

    /**
     * @param  array{company_relationship_id: int, service_type_id: int}  $data
     */
    public function create(array $data): TechnicianServiceType
    {
        return DB::transaction(function () use ($data): TechnicianServiceType {
            /** @var TechnicianServiceType|null $trashed */
            $trashed = TechnicianServiceType::withTrashed()
                ->where('company_relationship_id', $data['company_relationship_id'])
                ->where('service_type_id', $data['service_type_id'])
                ->first();

            if ($trashed !== null) {
                if ($trashed->trashed()) {
                    $trashed->restore();
                }

                return $trashed->fresh() ?? $trashed;
            }

            return TechnicianServiceType::query()->create([
                'company_relationship_id' => $data['company_relationship_id'],
                'service_type_id' => $data['service_type_id'],
            ]);
        });
    }

    /**
     * @param  array{company_relationship_id: int, service_type_id: int}  $data
     */
    public function update(TechnicianServiceType $row, array $data): TechnicianServiceType
    {
        return DB::transaction(function () use ($row, $data): TechnicianServiceType {
            $row->update([
                'company_relationship_id' => $data['company_relationship_id'],
                'service_type_id' => $data['service_type_id'],
            ]);

            return $row->fresh() ?? $row;
        });
    }

    public function delete(TechnicianServiceType $row): void
    {
        if ($row->trashed()) {
            return;
        }

        DB::transaction(function () use ($row): void {
            $row->delete();
        });
    }

    /**
     * @return list<array{id: int, service_type_id: int, service_type_label: string, service_type_color: string|null}>
     */
    public function forTechnician(CompanyRelationship $relationship): array
    {
        return TechnicianServiceType::query()
            ->with('serviceType:id,name,code,color')
            ->where('company_relationship_id', $relationship->id)
            ->orderBy('service_type_id')
            ->get()
            ->map(function (TechnicianServiceType $row): array {
                $serviceType = $row->serviceType;

                return [
                    'id' => $row->id,
                    'service_type_id' => $row->service_type_id,
                    'service_type_label' => $serviceType
                        ? ($serviceType->code ? "{$serviceType->code} — {$serviceType->name}" : $serviceType->name)
                        : '',
                    'service_type_color' => $serviceType?->color,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Replace the technician's service types with the given service type ids.
     *
     * @param  list<int>  $serviceTypeIds
     * @return list<array{id: int, service_type_id: int, service_type_label: string, service_type_color: string|null}>
     */
    public function syncForTechnician(CompanyRelationship $relationship, array $serviceTypeIds): array
    {
        return DB::transaction(function () use ($relationship, $serviceTypeIds): array {
            $wanted = [];
            foreach ($serviceTypeIds as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $wanted[$id] = $id;
                }
            }
            $wantedIds = array_values($wanted);

            $existing = TechnicianServiceType::withTrashed()
                ->where('company_relationship_id', $relationship->id)
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

                TechnicianServiceType::query()->create([
                    'company_relationship_id' => $relationship->id,
                    'service_type_id' => $serviceTypeId,
                ]);
            }

            return $this->forTechnician($relationship);
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

    /**
     * @return list<array{id: int, label: string}>
     */
    public function technicianOptions(): array
    {
        /** @var Collection<int, CompanyRelationship> $relationships */
        $relationships = CompanyRelationship::query()
            ->with('relatedCompany:id,name,tradename')
            ->where('kind', CompanyRelationshipKind::Technician->value)
            ->orderBy('id')
            ->get();

        return $relationships
            ->map(fn (CompanyRelationship $relationship): array => [
                'id' => $relationship->id,
                'label' => $this->relationshipLabel($relationship),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, company_relationship_id: int, service_type_id: int}
     */
    public function toFormData(TechnicianServiceType $row): array
    {
        return [
            'id' => $row->id,
            'company_relationship_id' => $row->company_relationship_id,
            'service_type_id' => $row->service_type_id,
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     company_relationship_id: int,
     *     service_type_id: int,
     *     technician_label: string|null,
     *     service_type_label: string|null,
     *     service_type_color: string|null,
     *     created_at: string|null
     * }
     */
    public function toListItem(TechnicianServiceType $row): array
    {
        $row->loadMissing([
            'companyRelationship.relatedCompany:id,name,tradename',
            'serviceType:id,name,code,color',
        ]);

        $relationship = $row->companyRelationship;
        $serviceType = $row->serviceType;

        return [
            'id' => $row->id,
            'company_relationship_id' => $row->company_relationship_id,
            'service_type_id' => $row->service_type_id,
            'technician_label' => $relationship !== null ? $this->relationshipLabel($relationship) : null,
            'service_type_label' => $serviceType?->name,
            'service_type_color' => $serviceType?->color,
            'created_at' => $row->created_at?->toIso8601String(),
        ];
    }

    private function relationshipLabel(CompanyRelationship $relationship): string
    {
        $company = $relationship->relatedCompany;
        $name = $company?->tradename ?: $company?->name ?: '#'.$relationship->id;

        return $name;
    }
}
