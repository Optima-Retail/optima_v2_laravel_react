<?php

declare(strict_types=1);

namespace App\Domain\Contracts\Services;

use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\Companies\Support\CompanyMemberUsers;
use App\Domain\Config\NumberingPatterns\Enums\NumberingResource;
use App\Domain\Config\NumberingPatterns\Services\NumberingPatternService;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\Contract;
use App\Models\ContractInvoicingAggregation;
use App\Models\ContractIteration;
use App\Models\ContractStatus;
use App\Models\Establishment;
use App\Models\FormTemplate;
use App\Models\Language;
use App\Models\WorkOrderType;
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
     * @param  list<int>  $includeUserIds
     * @return list<array{id: int, label: string}>
     */
    public function userOptions(Company $owner, array $includeUserIds = []): array
    {
        return CompanyMemberUsers::options($owner, $includeUserIds);
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function workOrderTypeOptions(): array
    {
        return WorkOrderType::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (WorkOrderType $type): array => [
                'id' => $type->id,
                'label' => $type->code ? "{$type->name} ({$type->code})" : $type->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function formTemplateOptions(Company $owner): array
    {
        return FormTemplate::query()
            ->where('company_id', $owner->id)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (FormTemplate $template): array => [
                'id' => $template->id,
                'label' => $template->name,
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
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null, contract_status_id?: string|null, created_from?: string|null, created_to?: string|null}  $filters
     * @return LengthAwarePaginator<int, Contract>
     */
    public function paginateForOwner(Company $owner, array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $contractStatusId = trim((string) ($filters['contract_status_id'] ?? ''));
        $createdFrom = trim((string) ($filters['created_from'] ?? ''));
        $createdTo = trim((string) ($filters['created_to'] ?? ''));
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
            ->when($contractStatusId !== '', fn ($query) => $query->where('contract_status_id', (int) $contractStatusId))
            ->when($createdFrom !== '', fn ($query) => $query->whereDate('created_at', '>=', $createdFrom))
            ->when($createdTo !== '', fn ($query) => $query->whereDate('created_at', '<=', $createdTo))
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null, contract_status_id?: string|null, created_from?: string|null, created_to?: string|null}  $filters
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
            $this->syncSchedule($contract, $data);

            return $contract->load([
                'company',
                'contractStatus',
                'responsibleUser',
                'language',
                'establishments',
                'iterations',
                'invoicingAggregations',
            ]);
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
            $this->syncSchedule($contract, $data);

            return $contract->fresh([
                'company',
                'contractStatus',
                'responsibleUser',
                'language',
                'establishments',
                'iterations',
                'invoicingAggregations',
            ]) ?? $contract;
        });
    }

    public function delete(Contract $contract): void
    {
        if ($contract->trashed()) {
            return;
        }

        DB::transaction(function () use ($contract): void {
            $contract->establishments()->detach();
            $contract->iterations()->delete();
            $contract->invoicingAggregations()->delete();
            $contract->delete();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function toFormData(Contract $contract): array
    {
        $contract->loadMissing(['establishments', 'iterations', 'invoicingAggregations']);

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
            'iterations' => $contract->iterations
                ->map(fn (ContractIteration $iteration): array => $this->iterationToFormData($iteration))
                ->values()
                ->all(),
            'invoicing_aggregations' => $contract->invoicingAggregations
                ->map(fn (ContractInvoicingAggregation $aggregation): array => $this->aggregationToFormData($aggregation))
                ->values()
                ->all(),
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

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncSchedule(Contract $contract, array $data): void
    {
        /** @var list<array<string, mixed>> $aggregationsPayload */
        $aggregationsPayload = array_values((array) ($data['invoicing_aggregations'] ?? []));
        /** @var list<array<string, mixed>> $iterationsPayload */
        $iterationsPayload = array_values((array) ($data['iterations'] ?? []));

        $tempKeyToId = [];
        $keptAggregationIds = [];

        foreach ($aggregationsPayload as $row) {
            $attributes = [
                'subject' => $row['subject'] ?? null,
                'billing_frequency' => $row['billing_frequency'] ?? 'monthly',
                'billing_day' => $row['billing_day'] ?? null,
                'billing_cycle_start' => $row['billing_cycle_start'] ?? null,
                'per_establishment' => (bool) ($row['per_establishment'] ?? false),
            ];

            $existingId = isset($row['id']) ? (int) $row['id'] : 0;
            $aggregation = null;

            if ($existingId > 0) {
                $aggregation = ContractInvoicingAggregation::query()
                    ->where('contract_id', $contract->id)
                    ->whereKey($existingId)
                    ->first();
            }

            if ($aggregation !== null) {
                $aggregation->update($attributes);
            } else {
                $aggregation = $contract->invoicingAggregations()->create($attributes);
            }

            $keptAggregationIds[] = $aggregation->id;
            $tempKey = isset($row['temp_key']) ? trim((string) $row['temp_key']) : '';
            if ($tempKey !== '') {
                $tempKeyToId[$tempKey] = $aggregation->id;
            }
        }

        $aggregationsToRemove = ContractInvoicingAggregation::query()
            ->where('contract_id', $contract->id)
            ->when(
                $keptAggregationIds !== [],
                fn ($query) => $query->whereNotIn('id', $keptAggregationIds),
            )
            ->get();

        foreach ($aggregationsToRemove as $aggregation) {
            $aggregation->iterations()->update(['invoicing_aggregation_id' => null]);
            $aggregation->delete();
        }

        $keptIterationIds = [];

        foreach ($iterationsPayload as $row) {
            $aggregationId = null;
            if (! empty($row['invoicing_aggregation_id'])) {
                $candidate = (int) $row['invoicing_aggregation_id'];
                if (in_array($candidate, $keptAggregationIds, true)) {
                    $aggregationId = $candidate;
                }
            } elseif (! empty($row['invoicing_aggregation_temp_key'])) {
                $aggregationId = $tempKeyToId[(string) $row['invoicing_aggregation_temp_key']] ?? null;
            }

            $attributes = [
                'subject' => $row['subject'] ?? null,
                'work_order_type_id' => (int) $row['work_order_type_id'],
                'starts_on' => $row['starts_on'],
                'ends_on' => $row['ends_on'],
                'periodicity' => $row['periodicity'],
                'periodicity_kind' => $row['periodicity_kind'],
                'interval' => $row['interval'] ?? null,
                'weekdays' => $row['weekdays'] ?? [],
                'month_days' => $row['month_days'] ?? [],
                'months' => $row['months'] ?? [],
                'cost_amount' => $row['cost_amount'],
                'establishment_ids' => $row['establishment_ids'] ?? [],
                'form_template_id' => $row['form_template_id'] ?? null,
                'invoicing_aggregation_id' => $aggregationId,
            ];

            $existingId = isset($row['id']) ? (int) $row['id'] : 0;
            $iteration = null;

            if ($existingId > 0) {
                $iteration = ContractIteration::query()
                    ->where('contract_id', $contract->id)
                    ->whereKey($existingId)
                    ->first();
            }

            if ($iteration !== null) {
                $iteration->update($attributes);
            } else {
                $iteration = $contract->iterations()->create($attributes);
            }

            $keptIterationIds[] = $iteration->id;
        }

        if ($keptIterationIds === []) {
            ContractIteration::query()->where('contract_id', $contract->id)->delete();
        } else {
            ContractIteration::query()
                ->where('contract_id', $contract->id)
                ->whereNotIn('id', $keptIterationIds)
                ->delete();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function iterationToFormData(ContractIteration $iteration): array
    {
        return [
            'id' => $iteration->id,
            'temp_key' => null,
            'subject' => $iteration->subject,
            'work_order_type_id' => $iteration->work_order_type_id,
            'starts_on' => $iteration->starts_on?->format('Y-m-d'),
            'ends_on' => $iteration->ends_on?->format('Y-m-d'),
            'periodicity' => $iteration->periodicity?->value,
            'periodicity_kind' => $iteration->periodicity_kind?->value,
            'interval' => $iteration->interval,
            'weekdays' => array_map('intval', $iteration->weekdays ?? []),
            'month_days' => array_map('intval', $iteration->month_days ?? []),
            'months' => array_map('intval', $iteration->months ?? []),
            'cost_amount' => $iteration->cost_amount,
            'establishment_ids' => array_map('intval', $iteration->establishment_ids ?? []),
            'form_template_id' => $iteration->form_template_id,
            'invoicing_aggregation_id' => $iteration->invoicing_aggregation_id,
            'invoicing_aggregation_temp_key' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function aggregationToFormData(ContractInvoicingAggregation $aggregation): array
    {
        return [
            'id' => $aggregation->id,
            'temp_key' => null,
            'subject' => $aggregation->subject,
            'billing_frequency' => $aggregation->billing_frequency?->value ?? 'monthly',
            'billing_day' => $aggregation->billing_day,
            'billing_cycle_start' => $aggregation->billing_cycle_start?->format('Y-m-d'),
            'per_establishment' => (bool) $aggregation->per_establishment,
        ];
    }
}
