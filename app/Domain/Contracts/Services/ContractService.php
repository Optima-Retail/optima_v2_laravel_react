<?php

declare(strict_types=1);

namespace App\Domain\Contracts\Services;

use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\Config\NumberingPatterns\Enums\NumberingResource;
use App\Domain\Config\NumberingPatterns\Services\NumberingPatternService;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\Establishment;
use App\Models\Language;
use App\Models\User;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ContractService
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
     * Establishments for accessible client companies (filterable by company_id on the client).
     *
     * @return list<array{id: int, label: string, company_id: int}>
     */
    public function establishmentOptions(Company $owner): array
    {
        $ids = $this->accessibleCompanyIds($owner);

        if ($ids === []) {
            return [];
        }

        return Establishment::query()
            ->whereIn('company_id', $ids)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'company_id'])
            ->map(fn (Establishment $establishment): array => [
                'id' => $establishment->id,
                'label' => $establishment->code
                    ? "{$establishment->name} ({$establishment->code})"
                    : $establishment->name,
                'company_id' => (int) $establishment->company_id,
            ])
            ->values()
            ->all();
    }

    /**
     * Active (`is_open`) statuses for contract forms.
     * Optionally keep a current inactive status so edit still shows the saved value.
     *
     * @return list<array{id: int, label: string, color: string|null}>
     */
    public function contractStatusOptions(?int $includeId = null): array
    {
        return ContractStatus::query()
            ->where(function ($query) use ($includeId): void {
                $query->where('is_open', true);

                if ($includeId !== null) {
                    $query->orWhere('id', $includeId);
                }
            })
            ->orderBy('lifecycle')
            ->orderBy('name')
            ->get(['id', 'name', 'color'])
            ->map(fn (ContractStatus $status): array => [
                'id' => $status->id,
                'label' => $status->name,
                'color' => $status->color,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function languageOptions(): array
    {
        return Language::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (Language $language): array => [
                'id' => $language->id,
                'label' => "{$language->name} ({$language->code})",
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function userOptions(): array
    {
        return User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'label' => $user->email
                    ? "{$user->name} ({$user->email})"
                    : $user->name,
            ])
            ->values()
            ->all();
    }

    public function defaultContractStatusId(): ?int
    {
        $open = ContractStatus::query()
            ->where('is_open', true)
            ->orderBy('lifecycle')
            ->orderBy('id')
            ->value('id');

        return $open !== null ? (int) $open : null;
    }

    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, Contract>
     */
    public function paginateForOwner(Company $owner, array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort(
            $filters,
            ['id', 'code', 'description', 'signed_at', 'total_amount', 'created_at'],
            'id',
        );

        return Contract::query()
            ->with(['company', 'contractStatus', 'responsibleUser', 'language'])
            ->whereIn('company_id', $this->accessibleCompanyIds($owner))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('work_order_subject', 'like', "%{$search}%");
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
        $page = $this->paginateForOwner($owner, $filters, $perPage);
        $brandNames = $this->brandNamesForContracts($owner, $page->getCollection());

        return $page->through(
            fn (Contract $contract): array => $this->toListItem(
                $contract,
                $brandNames->get((int) $contract->company_id),
            ),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Company $owner, array $data): Contract
    {
        return DB::transaction(function () use ($owner, $data): Contract {
            $attributes = $this->attributes($data);
            $resource = NumberingResource::Contracts->value;
            $numbering = app(NumberingPatternService::class);

            $existing = $numbering->findForResource($owner, $resource);

            if ($existing === null || $existing->is_active) {
                $attributes['code'] = $numbering->allocateNext($owner, $resource);
            }

            $contract = Contract::query()->create($attributes);
            $this->syncEstablishments($contract, $data['establishment_ids'] ?? []);

            return $contract->load(['company', 'contractStatus', 'responsibleUser', 'language', 'establishments']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Contract $contract, array $data): Contract
    {
        return DB::transaction(function () use ($contract, $data): Contract {
            $contract->update($this->attributes($data));
            $this->syncEstablishments($contract, $data['establishment_ids'] ?? []);

            return $contract->fresh(['company', 'contractStatus', 'responsibleUser', 'language', 'establishments']) ?? $contract;
        });
    }

    public function delete(Contract $contract): void
    {
        if ($contract->trashed()) {
            return;
        }

        DB::transaction(function () use ($contract): void {
            $contract->establishments()->detach();
            $contract->delete();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function toFormData(Contract $contract): array
    {
        $contract->loadMissing(['establishments']);

        return [
            'id' => $contract->id,
            'code' => $contract->code,
            'company_id' => $contract->company_id,
            'responsible_user_id' => $contract->responsible_user_id,
            'contract_status_id' => $contract->contract_status_id,
            'language_id' => $contract->language_id,
            'description' => $contract->description,
            'work_order_subject' => $contract->work_order_subject,
            'signed_at' => $contract->signed_at?->format('Y-m-d'),
            'canceled_at' => $contract->canceled_at?->format('Y-m-d'),
            'establishment_ids' => $contract->establishments->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toListItem(Contract $contract, ?string $brandName = null): array
    {
        return [
            'id' => $contract->id,
            'code' => $contract->code,
            'description' => $contract->description,
            'company_name' => $contract->company?->name,
            'brand_name' => $brandName,
            'status_name' => $contract->contractStatus?->name,
            'status_color' => $contract->contractStatus?->color,
            'signed_at' => $contract->signed_at?->toDateString(),
            'total_amount' => $contract->total_amount,
            'created_at' => $contract->created_at?->toIso8601String(),
        ];
    }

    /**
     * Brand lives on the owner→client customer relationship (legacy cliente.marca_id).
     *
     * @param  Collection<int, Contract>  $contracts
     * @return Collection<int, string|null>
     */
    private function brandNamesForContracts(Company $owner, Collection $contracts): Collection
    {
        $clientIds = $contracts
            ->pluck('company_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($clientIds === []) {
            return collect();
        }

        return CompanyRelationship::query()
            ->with('brand')
            ->where('owner_company_id', $owner->id)
            ->where('kind', CompanyRelationshipKind::Customer->value)
            ->whereIn('related_company_id', $clientIds)
            ->get(['related_company_id', 'brand_id'])
            ->mapWithKeys(fn (CompanyRelationship $relationship): array => [
                (int) $relationship->related_company_id => $relationship->brand?->name,
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'code' => $data['code'] ?? null,
            'company_id' => $data['company_id'] ?? null,
            'responsible_user_id' => $data['responsible_user_id'] ?? null,
            'contract_status_id' => $data['contract_status_id'] ?? null,
            'language_id' => $data['language_id'] ?? null,
            'description' => $data['description'] ?? null,
            'work_order_subject' => $data['work_order_subject'] ?? null,
            'signed_at' => $data['signed_at'] ?? null,
            'canceled_at' => $data['canceled_at'] ?? null,
        ];
    }

    /**
     * @param  list<int|string>  $establishmentIds
     */
    private function syncEstablishments(Contract $contract, array $establishmentIds): void
    {
        $ids = collect($establishmentIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $contract->establishments()->sync($ids);
    }
}
