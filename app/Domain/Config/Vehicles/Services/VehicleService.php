<?php

declare(strict_types=1);

namespace App\Domain\Config\Vehicles\Services;

use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Models\CompanyRelationship;
use App\Models\Vehicle;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class VehicleService
{
    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, Vehicle>
     */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort(
            $filters,
            ['id', 'brand', 'model', 'license_plate', 'created_at'],
            'brand',
        );

        return Vehicle::query()
            ->with(['companyRelationship.relatedCompany:id,name,tradename'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner
                        ->where('brand', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhere('license_plate', 'like', "%{$search}%");
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
            ->through(fn (Vehicle $vehicle): array => $this->toListItem($vehicle));
    }

    /**
     * @param  array{
     *     brand?: string|null,
     *     model?: string|null,
     *     license_plate?: string|null,
     *     company_relationship_id: int
     * }  $data
     */
    public function create(array $data): Vehicle
    {
        return DB::transaction(function () use ($data): Vehicle {
            return Vehicle::query()->create([
                'brand' => $this->nullableString($data['brand'] ?? null),
                'model' => $this->nullableString($data['model'] ?? null),
                'license_plate' => $this->nullableString($data['license_plate'] ?? null),
                'company_relationship_id' => $data['company_relationship_id'],
            ]);
        });
    }

    /**
     * @param  array{
     *     brand?: string|null,
     *     model?: string|null,
     *     license_plate?: string|null,
     *     company_relationship_id: int
     * }  $data
     */
    public function update(Vehicle $vehicle, array $data): Vehicle
    {
        return DB::transaction(function () use ($vehicle, $data): Vehicle {
            $vehicle->update([
                'brand' => $this->nullableString($data['brand'] ?? null),
                'model' => $this->nullableString($data['model'] ?? null),
                'license_plate' => $this->nullableString($data['license_plate'] ?? null),
                'company_relationship_id' => $data['company_relationship_id'],
            ]);

            return $vehicle->fresh() ?? $vehicle;
        });
    }

    public function delete(Vehicle $vehicle): void
    {
        if ($vehicle->trashed()) {
            return;
        }

        DB::transaction(function () use ($vehicle): void {
            $vehicle->delete();
        });
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
     * @return list<array{id: int, brand: string|null, model: string|null, license_plate: string|null}>
     */
    public function forTechnician(CompanyRelationship $relationship): array
    {
        return Vehicle::query()
            ->where('company_relationship_id', $relationship->id)
            ->orderBy('id')
            ->get()
            ->map(fn (Vehicle $vehicle): array => [
                'id' => $vehicle->id,
                'brand' => $vehicle->brand,
                'model' => $vehicle->model,
                'license_plate' => $vehicle->license_plate,
            ])
            ->values()
            ->all();
    }

    /**
     * Replace the technician's vehicles with the given rows.
     * Soft-deletes vehicles not present in the payload; updateOrCreates the rest.
     *
     * @param  list<array{id?: int|null, brand?: string|null, model?: string|null, license_plate?: string|null}>  $rows
     * @return list<array{id: int, brand: string|null, model: string|null, license_plate: string|null}>
     */
    public function syncForTechnician(CompanyRelationship $relationship, array $rows): array
    {
        return DB::transaction(function () use ($relationship, $rows): array {
            $keepIds = [];

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $brand = $this->nullableString($row['brand'] ?? null);
                $model = $this->nullableString($row['model'] ?? null);
                $licensePlate = $this->nullableString($row['license_plate'] ?? null);

                if ($brand === null && $model === null && $licensePlate === null) {
                    continue;
                }

                $id = isset($row['id']) && $row['id'] !== null && $row['id'] !== ''
                    ? (int) $row['id']
                    : null;

                $attributes = [
                    'brand' => $brand,
                    'model' => $model,
                    'license_plate' => $licensePlate,
                    'company_relationship_id' => $relationship->id,
                ];

                if ($id !== null && $id > 0) {
                    $existing = Vehicle::query()
                        ->where('company_relationship_id', $relationship->id)
                        ->whereKey($id)
                        ->first();

                    if ($existing !== null) {
                        $existing->update($attributes);
                        $keepIds[] = $existing->id;

                        continue;
                    }
                }

                $created = Vehicle::query()->create($attributes);
                $keepIds[] = $created->id;
            }

            Vehicle::query()
                ->where('company_relationship_id', $relationship->id)
                ->when(
                    $keepIds === [],
                    fn ($query) => $query,
                    fn ($query) => $query->whereNotIn('id', $keepIds),
                )
                ->get()
                ->each(function (Vehicle $vehicle): void {
                    $vehicle->delete();
                });

            return $this->forTechnician($relationship);
        });
    }

    /**
     * @return array{
     *     id: int,
     *     brand: string|null,
     *     model: string|null,
     *     license_plate: string|null,
     *     company_relationship_id: int|null
     * }
     */
    public function toFormData(Vehicle $vehicle): array
    {
        return [
            'id' => $vehicle->id,
            'brand' => $vehicle->brand,
            'model' => $vehicle->model,
            'license_plate' => $vehicle->license_plate,
            'company_relationship_id' => $vehicle->company_relationship_id,
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     brand: string|null,
     *     model: string|null,
     *     license_plate: string|null,
     *     company_relationship_id: int|null,
     *     technician_label: string|null,
     *     created_at: string|null
     * }
     */
    public function toListItem(Vehicle $vehicle): array
    {
        $vehicle->loadMissing('companyRelationship.relatedCompany:id,name,tradename');

        $relationship = $vehicle->companyRelationship;

        return [
            'id' => $vehicle->id,
            'brand' => $vehicle->brand,
            'model' => $vehicle->model,
            'license_plate' => $vehicle->license_plate,
            'company_relationship_id' => $vehicle->company_relationship_id,
            'technician_label' => $relationship !== null ? $this->relationshipLabel($relationship) : null,
            'created_at' => $vehicle->created_at?->toIso8601String(),
        ];
    }

    private function relationshipLabel(CompanyRelationship $relationship): string
    {
        $company = $relationship->relatedCompany;

        if ($company?->tradename) {
            return "{$company->name} ({$company->tradename})";
        }

        return $company?->name ?? "#{$relationship->id}";
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
