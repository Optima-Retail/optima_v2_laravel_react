<?php

declare(strict_types=1);

namespace App\Domain\WorkOrders\Services;

use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\Establishment;
use App\Models\ServiceType;
use App\Models\WorkOrderTypeServiceType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Technician picker for estimate "Presupuestos solicitados" / OT technicians.
 * Mirrors optima_back ModalTecnicos tabs (nearby, history, rating, all).
 */
final class TechnicianSearchService
{
    public const TAB_NEARBY = 'nearby';

    public const TAB_HISTORY = 'history';

    public const TAB_RATING = 'rating';

    public const TAB_ALL = 'all';

    /**
     * @param  array{
     *     establishment_id: int,
     *     work_order_type_id?: int|null,
     *     tab?: string|null,
     *     search?: string|null,
     *     service_type_ids?: list<int|string>|null,
     *     radius_km?: int|float|null,
     *     prl_ok?: bool|null,
     *     page?: int|null,
     *     per_page?: int|null
     * }  $filters
     * @return array{
     *     data: list<array<string, mixed>>,
     *     meta: array<string, mixed>,
     *     current_page: int,
     *     last_page: int,
     *     per_page: int,
     *     total: int
     * }
     */
    public function search(Company $owner, array $filters): array
    {
        $establishmentId = (int) ($filters['establishment_id'] ?? 0);
        $establishment = Establishment::query()->findOrFail($establishmentId);
        $tab = (string) ($filters['tab'] ?? self::TAB_NEARBY);
        if (! in_array($tab, [self::TAB_NEARBY, self::TAB_HISTORY, self::TAB_RATING, self::TAB_ALL], true)) {
            $tab = self::TAB_NEARBY;
        }

        $search = trim((string) ($filters['search'] ?? ''));
        $radiusKm = max(0, (float) ($filters['radius_km'] ?? 50));
        $prlOk = filter_var($filters['prl_ok'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(50, max(10, (int) ($filters['per_page'] ?? 25)));
        $workOrderTypeId = filled($filters['work_order_type_id'] ?? null) ? (int) $filters['work_order_type_id'] : null;

        $serviceTypeIds = array_values(array_unique(array_filter(
            array_map('intval', (array) ($filters['service_type_ids'] ?? [])),
            fn (int $id): bool => $id > 0,
        )));

        if ($serviceTypeIds === [] && $workOrderTypeId !== null) {
            $serviceTypeIds = WorkOrderTypeServiceType::query()
                ->where('work_order_type_id', $workOrderTypeId)
                ->pluck('service_type_id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        }

        $blacklistedIds = DB::table('establishment_technician_blacklist')
            ->where('establishment_id', $establishment->id)
            ->pluck('company_relationship_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $favoriteIds = DB::table('establishment_favorite_technicians')
            ->where('establishment_id', $establishment->id)
            ->pluck('company_relationship_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $historyIds = $this->historyRelationshipIds($establishment->id);

        $query = CompanyRelationship::query()
            ->with('relatedCompany:id,name,tradename,logo,phone,latitude,longitude,is_active')
            ->where('owner_company_id', $owner->id)
            ->where('kind', CompanyRelationshipKind::Technician->value)
            ->whereHas('relatedCompany', fn ($q) => $q->where('is_active', true))
            ->when($prlOk, fn ($q) => $q->where('has_health_and_safety', true))
            ->when($serviceTypeIds !== [], function ($q) use ($serviceTypeIds): void {
                $q->whereExists(function ($sub) use ($serviceTypeIds): void {
                    $sub->selectRaw('1')
                        ->from('technician_service_types')
                        ->whereColumn('technician_service_types.company_relationship_id', 'company_relationships.id')
                        ->whereNull('technician_service_types.deleted_at')
                        ->whereIn('technician_service_types.service_type_id', $serviceTypeIds);
                });
            })
            ->when($search !== '', function ($q) use ($search): void {
                $q->whereHas('relatedCompany', function ($company) use ($search): void {
                    $company->where(function ($inner) use ($search): void {
                        $inner->where('name', 'like', "%{$search}%")
                            ->orWhere('tradename', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
                });
            });

        if ($tab === self::TAB_HISTORY) {
            if ($historyIds === []) {
                return $this->emptyPage($page, $perPage, $establishment, $workOrderTypeId, $serviceTypeIds);
            }
            $query->whereIn('id', $historyIds);
        }

        /** @var Collection<int, CompanyRelationship> $rows */
        $rows = $query->get();

        [$estLat, $estLng, $hasCoords] = $this->resolveEstablishmentCoordinates($establishment);

        $mapped = $rows->map(function (CompanyRelationship $relationship) use (
            $estLat,
            $estLng,
            $hasCoords,
            $favoriteIds,
            $historyIds,
            $blacklistedIds,
        ): array {
            $company = $relationship->relatedCompany;
            $techLat = $company?->latitude !== null ? (float) $company->latitude : null;
            $techLng = $company?->longitude !== null ? (float) $company->longitude : null;
            $distance = ($hasCoords && $techLat !== null && $techLng !== null)
                ? $this->haversineKm($estLat, $estLng, $techLat, $techLng)
                : null;

            $name = $company?->tradename ?: $company?->name ?: '#'.$relationship->id;

            return [
                'id' => $relationship->id,
                'label' => $name,
                'logo_url' => $company?->logoUrl(),
                'phone' => $company?->phone,
                'optima_score' => $relationship->optima_score !== null ? (float) $relationship->optima_score : null,
                'customer_score' => $relationship->customer_score !== null ? (float) $relationship->customer_score : null,
                'average_score' => $relationship->average_score !== null ? (float) $relationship->average_score : null,
                'distance_km' => $distance !== null ? round($distance, 1) : null,
                'is_favorite' => in_array($relationship->id, $favoriteIds, true),
                'is_blacklisted' => in_array($relationship->id, $blacklistedIds, true),
                'has_history' => in_array($relationship->id, $historyIds, true),
                'has_health_and_safety' => (bool) $relationship->has_health_and_safety,
            ];
        });

        if (in_array($tab, [self::TAB_NEARBY, self::TAB_RATING], true) && $hasCoords && $radiusKm > 0) {
            $mapped = $mapped->filter(
                fn (array $row): bool => $row['distance_km'] === null || $row['distance_km'] <= $radiusKm,
            );
        }

        $sorted = match ($tab) {
            self::TAB_HISTORY => $mapped->sortBy([
                ['is_blacklisted', 'asc'],
                ['is_favorite', 'desc'],
                ['average_score', 'desc'],
                ['label', 'asc'],
            ]),
            self::TAB_RATING => $mapped->sortBy([
                ['is_blacklisted', 'asc'],
                ['is_favorite', 'desc'],
                ['average_score', 'desc'],
                ['distance_km', 'asc'],
                ['label', 'asc'],
            ]),
            self::TAB_ALL => $mapped->sortBy([
                ['is_blacklisted', 'asc'],
                ['is_favorite', 'desc'],
                ['label', 'asc'],
            ]),
            default => $mapped->sortBy([
                ['is_blacklisted', 'asc'],
                ['is_favorite', 'desc'],
                ['distance_km', 'asc'],
                ['average_score', 'desc'],
                ['label', 'asc'],
            ]),
        };

        $items = $sorted->values();
        $total = $items->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $slice = $items->slice(($page - 1) * $perPage, $perPage)->values()->all();

        return [
            'data' => $slice,
            'meta' => [
                'tab' => $tab,
                'establishment_id' => $establishment->id,
                'establishment_name' => $establishment->name,
                'has_coordinates' => $hasCoords,
                'selected_service_type_ids' => $serviceTypeIds,
                'service_type_options' => $this->serviceTypeOptions($workOrderTypeId),
            ],
            'current_page' => $page,
            'last_page' => $lastPage,
            'per_page' => $perPage,
            'total' => $total,
        ];
    }

    /**
     * @return list<int>
     */
    private function historyRelationshipIds(int $establishmentId): array
    {
        return DB::table('work_order_technicians')
            ->join('work_orders', 'work_orders.id', '=', 'work_order_technicians.work_order_id')
            ->where('work_orders.establishment_id', $establishmentId)
            ->whereNull('work_orders.deleted_at')
            ->distinct()
            ->pluck('work_order_technicians.company_relationship_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    private function serviceTypeOptions(?int $workOrderTypeId): array
    {
        $query = ServiceType::query()->orderBy('name');

        if ($workOrderTypeId !== null) {
            $ids = WorkOrderTypeServiceType::query()
                ->where('work_order_type_id', $workOrderTypeId)
                ->pluck('service_type_id');

            if ($ids->isNotEmpty()) {
                $query->whereIn('id', $ids);
            }
        }

        return $query
            ->get(['id', 'name', 'code'])
            ->map(fn (ServiceType $type): array => [
                'id' => $type->id,
                'label' => $type->code ? "{$type->code} — {$type->name}" : $type->name,
            ])
            ->values()
            ->all();
    }

    /**
     * Prefer establishment coordinates; fall back to the client company when the site has none.
     *
     * @return array{0: float|null, 1: float|null, 2: bool}
     */
    private function resolveEstablishmentCoordinates(Establishment $establishment): array
    {
        $estLat = $establishment->latitude !== null ? (float) $establishment->latitude : null;
        $estLng = $establishment->longitude !== null ? (float) $establishment->longitude : null;

        if ($estLat !== null && $estLng !== null) {
            return [$estLat, $estLng, true];
        }

        $establishment->loadMissing('company:id,latitude,longitude');
        $companyLat = $establishment->company?->latitude !== null
            ? (float) $establishment->company->latitude
            : null;
        $companyLng = $establishment->company?->longitude !== null
            ? (float) $establishment->company->longitude
            : null;

        if ($companyLat !== null && $companyLng !== null) {
            return [$companyLat, $companyLng, true];
        }

        return [null, null, false];
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * @param  list<int>  $serviceTypeIds
     * @return array{
     *     data: list<array<string, mixed>>,
     *     meta: array<string, mixed>,
     *     current_page: int,
     *     last_page: int,
     *     per_page: int,
     *     total: int
     * }
     */
    private function emptyPage(
        int $page,
        int $perPage,
        Establishment $establishment,
        ?int $workOrderTypeId,
        array $serviceTypeIds,
    ): array {
        return [
            'data' => [],
            'meta' => [
                'tab' => self::TAB_HISTORY,
                'establishment_id' => $establishment->id,
                'establishment_name' => $establishment->name,
                'has_coordinates' => $this->resolveEstablishmentCoordinates($establishment)[2],
                'selected_service_type_ids' => $serviceTypeIds,
                'service_type_options' => $this->serviceTypeOptions($workOrderTypeId),
            ],
            'current_page' => $page,
            'last_page' => 1,
            'per_page' => $perPage,
            'total' => 0,
        ];
    }
}
