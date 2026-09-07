<?php

declare(strict_types=1);

namespace App\Domain\Companies\Services;

use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Models\Company;
use App\Models\Delegation;
use App\Models\Establishment;
use App\Models\Timezone;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class EstablishmentService
{
    /**
     * Client company IDs linked to the owner (kind = customer).
     *
     * @return list<int>
     */
    public function accessibleCompanyIds(Company $owner): array
    {
        return $owner->ownedRelationships()
            ->where('kind', CompanyRelationshipKind::Customer->value)
            ->pluck('related_company_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function clientCompanyOptions(Company $owner): array
    {
        $ids = $this->accessibleCompanyIds($owner);

        if ($ids === []) {
            return [];
        }

        return Company::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name', 'tax_id'])
            ->map(fn (Company $company): array => [
                'id' => $company->id,
                'label' => $company->tax_id
                    ? "{$company->name} ({$company->tax_id})"
                    : $company->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, Establishment>
     */
    public function paginateForOwner(Company $owner, array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort($filters, ['id', 'name', 'code', 'city', 'created_at'], 'name');

        return Establishment::query()
            ->with(['company', 'country', 'timezone', 'billingCompany', 'delegation'])
            ->whereIn('company_id', $this->accessibleCompanyIds($owner))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%");
                });
            })
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateForWeb(Company $owner, array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        return $this->paginateForOwner($owner, $filters, $perPage)
            ->through(fn (Establishment $establishment): array => $this->toListItem($establishment));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Establishment
    {
        return DB::transaction(function () use ($data): Establishment {
            return Establishment::query()->create($this->attributes($data))->load(['company', 'country', 'timezone', 'delegation']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Establishment $establishment, array $data): Establishment
    {
        return DB::transaction(function () use ($establishment, $data): Establishment {
            $establishment->update($this->attributes($data));

            return $establishment->fresh(['company', 'country', 'timezone', 'billingCompany', 'delegation']) ?? $establishment;
        });
    }

    public function delete(Establishment $establishment): void
    {
        if ($establishment->trashed()) {
            return;
        }

        DB::transaction(function () use ($establishment): void {
            $establishment->softDeleteSafely();
        });
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function timezoneOptions(): array
    {
        return Timezone::query()
            ->orderBy('name')
            ->get(['id', 'name', 'timezone'])
            ->map(fn (Timezone $timezone): array => [
                'id' => $timezone->id,
                'label' => "{$timezone->name} ({$timezone->timezone})",
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function delegationOptions(): array
    {
        return Delegation::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Delegation $delegation): array => [
                'id' => $delegation->id,
                'label' => $delegation->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function toFormData(Establishment $establishment): array
    {
        return [
            'id' => $establishment->id,
            'company_id' => $establishment->company_id,
            'name' => $establishment->name,
            'code' => $establishment->code,
            'address_line_1' => $establishment->address_line_1,
            'address_line_2' => $establishment->address_line_2,
            'city' => $establishment->city,
            'province' => $establishment->province,
            'postal_code' => $establishment->postal_code,
            'country_id' => $establishment->country_id,
            'timezone_id' => $establishment->timezone_id,
            'delegation_id' => $establishment->delegation_id,
            'is_active' => $establishment->is_active,
            'billing_company_id' => $establishment->billing_company_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toListItem(Establishment $establishment): array
    {
        return [
            'id' => $establishment->id,
            'name' => $establishment->name,
            'code' => $establishment->code,
            'city' => $establishment->city,
            'company_name' => $establishment->company?->name,
            'delegation_name' => $establishment->delegation?->name,
            'is_active' => $establishment->is_active,
            'created_at' => $establishment->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        foreach ([
            'code', 'address_line_1', 'address_line_2', 'city', 'province', 'postal_code',
            'country_id', 'timezone_id', 'delegation_id', 'billing_company_id',
        ] as $key) {
            if (array_key_exists($key, $data) && $data[$key] === '') {
                $data[$key] = null;
            }
        }

        return $data;
    }
}
