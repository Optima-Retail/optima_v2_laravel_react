<?php

declare(strict_types=1);

namespace App\Domain\WorkOrders\Services;

use App\Domain\Chats\Enums\ChatDocumentType;
use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\Companies\Services\EstablishmentService;
use App\Domain\Companies\Support\CompanyMemberUsers;
use App\Domain\Config\Checklists\Enums\ChecklistDocumentType;
use App\Domain\Config\NumberingPatterns\Enums\NumberingResource;
use App\Domain\Config\NumberingPatterns\Services\NumberingPatternService;
use App\Domain\Config\TasksToPerform\Enums\TaskDocumentType;
use App\Domain\QualityScores\Services\QualityScoreProcessor;
use App\Domain\StatusChanges\Services\StatusChangeHistoryService;
use App\Domain\WorkOrders\Enums\WorkOrderStage;
use App\Models\Article;
use App\Models\ArticleClient;
use App\Models\Checklist;
use App\Models\ClientPriority;
use App\Models\ClientRate;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\Contract;
use App\Models\Establishment;
use App\Models\Requester;
use App\Models\TaskToPerform;
use App\Models\TechnicianAttendanceConfirmationType;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderChecklistCompletion;
use App\Models\WorkOrderLine;
use App\Models\WorkOrderStatus;
use App\Models\WorkOrderTechnician;
use App\Models\WorkOrderTechnicianStatus;
use App\Models\WorkOrderType;
use App\Policies\EstimatePolicy;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as LengthAwarePaginatorConcrete;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class WorkOrderService
{
    public function __construct(
        private readonly WorkOrderConfirmationService $confirmation,
        private readonly NumberingPatternService $numbering,
        private readonly QualityScoreProcessor $qualityScores,
        private readonly StatusChangeHistoryService $statusChanges,
        private readonly WorkOrderStatusCatalog $statuses,
    ) {}

    /**
     * @return list<int>
     */
    public function accessibleCompanyIds(Company $owner): array
    {
        $ids = $owner->ownedRelationships()
            ->where('kind', CompanyRelationshipKind::Customer->value)
            ->pluck('related_company_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $ids[] = (int) $owner->id;

        return array_values(array_unique($ids));
    }

    /**
     * Seed options for Inertia pages (selected establishments only). Full lists load via /select-options/establishments.
     *
     * @param  list<int>  $includeIds
     * @return list<array{id: int, label: string, company_id: int, company_name: string|null, company_logo_url: string|null, brand_name: string|null, currency_id: int|null, currency_label: string|null}>
     */
    public function establishmentOptions(Company $owner, array $includeIds = []): array
    {
        return $this->searchEstablishmentOptions($owner, includeIds: $includeIds, onlyIncludeIds: true);
    }

    /**
     * Lightweight options for async establishment pickers.
     *
     * @param  list<int>  $includeIds
     * @return list<array{id: int, label: string, company_id: int, company_name: string|null, company_logo_url: string|null, brand_name: string|null, currency_id: int|null, currency_label: string|null}>
     */
    public function searchEstablishmentOptions(
        Company $owner,
        ?string $search = null,
        array $includeIds = [],
        ?int $companyId = null,
        ?int $limit = 50,
        bool $onlyIncludeIds = false,
    ): array {
        $ids = $this->accessibleCompanyIds($owner);

        if ($ids === []) {
            return [];
        }

        if ($companyId !== null && $companyId > 0) {
            if (! in_array($companyId, $ids, true)) {
                return [];
            }

            $ids = [$companyId];
        }

        $includeIds = array_values(array_unique(array_filter(
            array_map('intval', $includeIds),
            fn (int $id): bool => $id > 0,
        )));

        if ($onlyIncludeIds) {
            if ($includeIds === []) {
                return [];
            }

            $brandNames = $this->brandNamesForCompanies($owner, $ids);

            return Establishment::query()
                ->with([
                    'company:id,name,logo',
                    'delegation:id,currency_id',
                    'delegation.currency:id,name,code',
                ])
                ->whereIn('id', $includeIds)
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'company_id', 'delegation_id'])
                ->map(fn (Establishment $establishment): array => $this->mapEstablishmentOption($establishment, $brandNames))
                ->values()
                ->all();
        }

        $needle = trim((string) $search);
        $brandNames = $this->brandNamesForCompanies($owner, $ids);

        $rows = Establishment::query()
            ->with([
                'company:id,name,logo',
                'delegation:id,currency_id',
                'delegation.currency:id,name,code',
            ])
            ->whereIn('company_id', $ids)
            ->where(function ($query) use ($includeIds): void {
                $query->where('is_active', true);

                if ($includeIds !== []) {
                    $query->orWhereIn('id', $includeIds);
                }
            })
            ->when($needle !== '', function ($query) use ($needle): void {
                $query->where(function ($inner) use ($needle): void {
                    $inner->where('name', 'like', "%{$needle}%")
                        ->orWhere('code', 'like', "%{$needle}%")
                        ->orWhere('store_code', 'like', "%{$needle}%");
                });
            })
            ->orderBy('name')
            ->when($limit !== null, fn ($query) => $query->limit($limit))
            ->get(['id', 'name', 'code', 'company_id', 'delegation_id'])
            ->map(fn (Establishment $establishment): array => $this->mapEstablishmentOption($establishment, $brandNames))
            ->values()
            ->all();

        if ($includeIds !== []) {
            $missingIds = array_values(array_diff(
                $includeIds,
                array_map(fn (array $row): int => (int) $row['id'], $rows),
            ));

            if ($missingIds !== []) {
                $extra = Establishment::query()
                    ->with([
                        'company:id,name,logo',
                        'delegation:id,currency_id',
                        'delegation.currency:id,name,code',
                    ])
                    ->whereIn('id', $missingIds)
                    ->orderBy('name')
                    ->get(['id', 'name', 'code', 'company_id', 'delegation_id'])
                    ->map(fn (Establishment $establishment): array => $this->mapEstablishmentOption($establishment, $brandNames))
                    ->all();

                $rows = array_values(array_merge($extra, $rows));
            }
        }

        return $rows;
    }

    /**
     * @param  list<int>  $companyIds
     * @return Collection<int, string|null>
     */
    private function brandNamesForCompanies(Company $owner, array $companyIds)
    {
        return CompanyRelationship::query()
            ->with('brand:id,name')
            ->where('owner_company_id', $owner->id)
            ->where('kind', CompanyRelationshipKind::Customer->value)
            ->whereIn('related_company_id', $companyIds)
            ->get(['related_company_id', 'brand_id'])
            ->mapWithKeys(fn (CompanyRelationship $relationship): array => [
                (int) $relationship->related_company_id => $relationship->brand?->name,
            ]);
    }

    /**
     * @param  Collection<int, string|null>  $brandNames
     * @return array{id: int, label: string, company_id: int, company_name: string|null, company_logo_url: string|null, brand_name: string|null, currency_id: int|null, currency_label: string|null}
     */
    private function mapEstablishmentOption(Establishment $establishment, $brandNames): array
    {
        $currency = $establishment->delegation?->currency;

        return [
            'id' => $establishment->id,
            'label' => $establishment->code
                ? "{$establishment->name} ({$establishment->code})"
                : $establishment->name,
            'company_id' => (int) $establishment->company_id,
            'company_name' => $establishment->company?->name,
            'company_logo_url' => $establishment->company?->logoUrl(),
            'brand_name' => $brandNames->get((int) $establishment->company_id),
            'currency_id' => $currency?->id !== null ? (int) $currency->id : null,
            'currency_label' => $currency?->name,
        ];
    }

    /**
     * Seed options for Inertia pages (selected contracts only). Full lists load via /select-options/contracts.
     *
     * @param  list<int>  $includeIds
     * @return list<array{id: int, label: string}>
     */
    public function contractOptions(Company $owner, array $includeIds = []): array
    {
        return $this->searchContractOptions($owner, includeIds: $includeIds, onlyIncludeIds: true);
    }

    /**
     * Lightweight options for async contract pickers.
     *
     * @param  list<int>  $includeIds
     * @return list<array{id: int, label: string}>
     */
    public function searchContractOptions(
        Company $owner,
        ?string $search = null,
        array $includeIds = [],
        ?int $companyId = null,
        ?int $limit = 50,
        bool $onlyIncludeIds = false,
    ): array {
        $companyIds = $this->accessibleCompanyIds($owner);

        if ($companyIds === []) {
            return [];
        }

        if ($companyId !== null && $companyId > 0) {
            if (! in_array($companyId, $companyIds, true)) {
                return [];
            }

            $companyIds = [$companyId];
        }

        $includeIds = array_values(array_unique(array_filter(
            array_map('intval', $includeIds),
            fn (int $id): bool => $id > 0,
        )));

        if ($onlyIncludeIds) {
            if ($includeIds === []) {
                return [];
            }

            return Contract::query()
                ->whereIn('id', $includeIds)
                ->orderByDesc('id')
                ->get(['id', 'code', 'description', 'work_order_subject'])
                ->map(fn (Contract $contract): array => $this->mapContractOption($contract))
                ->values()
                ->all();
        }

        $needle = trim((string) $search);

        $rows = Contract::query()
            ->whereIn('company_id', $companyIds)
            ->when($needle !== '', function ($query) use ($needle): void {
                $query->where(function ($inner) use ($needle): void {
                    $inner->where('code', 'like', "%{$needle}%")
                        ->orWhere('description', 'like', "%{$needle}%")
                        ->orWhere('work_order_subject', 'like', "%{$needle}%");
                });
            })
            ->orderByDesc('id')
            ->when($limit !== null, fn ($query) => $query->limit($limit))
            ->get(['id', 'code', 'description', 'work_order_subject'])
            ->map(fn (Contract $contract): array => $this->mapContractOption($contract))
            ->values()
            ->all();

        if ($includeIds !== []) {
            $missingIds = array_values(array_diff(
                $includeIds,
                array_map(fn (array $row): int => (int) $row['id'], $rows),
            ));

            if ($missingIds !== []) {
                $extra = Contract::query()
                    ->whereIn('id', $missingIds)
                    ->orderByDesc('id')
                    ->get(['id', 'code', 'description', 'work_order_subject'])
                    ->map(fn (Contract $contract): array => $this->mapContractOption($contract))
                    ->all();

                $rows = array_values(array_merge($extra, $rows));
            }
        }

        return $rows;
    }

    /**
     * @return array{id: int, label: string}
     */
    private function mapContractOption(Contract $contract): array
    {
        $label = $contract->code
            ?: $contract->description
            ?: $contract->work_order_subject
            ?: '#'.$contract->id;

        if ($contract->code && filled($contract->description)) {
            $label = $contract->code.' — '.$contract->description;
        }

        return [
            'id' => (int) $contract->id,
            'label' => $label,
        ];
    }

    /**
     * Prefill helpers when creating a document from `?contract_id=`.
     *
     * @return array{contract_id: int|null, establishment_id: int|null, subject: string|null}
     */
    public function defaultsFromContract(
        Company $owner,
        ?int $requestedContractId,
        ?int $requestedEstablishmentId = null,
    ): array {
        $companyIds = $this->accessibleCompanyIds($owner);
        $contractId = null;

        if ($requestedContractId !== null && $requestedContractId > 0 && $companyIds !== []) {
            $exists = Contract::query()
                ->whereKey($requestedContractId)
                ->whereIn('company_id', $companyIds)
                ->exists();

            $contractId = $exists ? $requestedContractId : null;
        }

        $establishmentId = null;

        if ($requestedEstablishmentId !== null && $requestedEstablishmentId > 0 && $companyIds !== []) {
            $exists = Establishment::query()
                ->whereKey($requestedEstablishmentId)
                ->whereIn('company_id', $companyIds)
                ->where('is_active', true)
                ->exists();

            $establishmentId = $exists ? $requestedEstablishmentId : null;
        }

        $subject = null;

        if ($contractId !== null) {
            $contract = Contract::query()
                ->with(['establishments' => fn ($query) => $query->select('establishments.id')])
                ->find($contractId);

            $subject = filled($contract?->work_order_subject) ? (string) $contract->work_order_subject : null;

            if ($establishmentId === null && $contract !== null && $companyIds !== []) {
                $linkedIds = $contract->establishments->pluck('id')->map(fn ($id) => (int) $id)->all();
                $candidates = Establishment::query()
                    ->whereIn('id', $linkedIds)
                    ->whereIn('company_id', $companyIds)
                    ->where('is_active', true)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                if (count($candidates) === 1) {
                    $establishmentId = $candidates[0];
                }
            }
        }

        return [
            'contract_id' => $contractId,
            'establishment_id' => $establishmentId,
            'subject' => $subject,
        ];
    }

    /**
     * @return list<array{
     *     id: int,
     *     label: string,
     *     color: string|null,
     *     is_open: bool,
     *     confirms_estimate: bool,
     *     rejects_to_estimate: bool,
     *     requires_confirmation: bool,
     *     requires_justification: bool
     * }>
     */
    public function statusOptions(WorkOrderStage $kind, ?int $currentStatusId = null): array
    {
        return $this->statuses->options($kind, $currentStatusId);
    }

    /**
     * @return list<array{id: int, label: string, color: string|null}>
     */
    public function typeOptions(): array
    {
        return WorkOrderType::query()
            ->orderBy('name')
            ->get(['id', 'name', 'color'])
            ->map(fn (WorkOrderType $type): array => [
                'id' => $type->id,
                'label' => $type->name,
                'color' => $type->color,
            ])
            ->values()
            ->all();
    }

    /**
     * Priorities for the WO form.
     *
     * Prod: options come from the establishment's client (`clientes_prioridades` /
     * `company_priority`), not the global catalog. When `$companyId` is null, returns
     * all priorities with `company_ids` so the UI can filter after establishment select.
     *
     * @param  list<int>  $includePriorityIds
     * @return list<array{id: int, label: string, color: string|null, company_ids: list<int>}>
     */
    public function priorityOptions(?int $companyId = null, array $includePriorityIds = []): array
    {
        $includePriorityIds = array_values(array_unique(array_filter(
            array_map('intval', $includePriorityIds),
            fn (int $id): bool => $id > 0,
        )));

        $priorities = ClientPriority::query()
            ->with(['companies:id'])
            ->when(
                $companyId !== null,
                fn ($query) => $query->where(function ($inner) use ($companyId, $includePriorityIds): void {
                    $inner->whereHas(
                        'companies',
                        fn ($companies) => $companies->where('companies.id', $companyId),
                    );

                    if ($includePriorityIds !== []) {
                        $inner->orWhereIn('client_priorities.id', $includePriorityIds);
                    }
                }),
            )
            ->orderBy('name')
            ->get(['id', 'name', 'color']);

        return $priorities
            ->map(fn (ClientPriority $priority): array => [
                'id' => (int) $priority->id,
                'label' => $priority->name,
                'color' => $priority->color,
                'company_ids' => $priority->companies
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Seed options for Inertia pages (selected users only). Full lists load via /select-options/users.
     *
     * @param  list<int>  $includeUserIds
     * @return list<array{id: int, label: string}>
     */
    public function userOptions(Company $owner, array $includeUserIds = []): array
    {
        unset($owner);

        return CompanyMemberUsers::optionsByIds($includeUserIds);
    }

    /**
     * Seed options for Inertia pages (selected requesters only). Full lists load via /select-options/requesters.
     *
     * @param  list<int>  $includeIds
     * @return list<array{id: int, label: string, company_id: int}>
     */
    public function requesterOptions(Company $owner, ?int $establishmentId = null, array $includeIds = []): array
    {
        return $this->searchRequesterOptions($owner, establishmentId: $establishmentId, includeIds: $includeIds, onlyIncludeIds: true);
    }

    /**
     * Lightweight options for async requester pickers.
     *
     * @param  list<int>  $includeIds
     * @return list<array{id: int, label: string, company_id: int}>
     */
    public function searchRequesterOptions(
        Company $owner,
        ?string $search = null,
        ?int $establishmentId = null,
        array $includeIds = [],
        ?int $limit = 50,
        bool $onlyIncludeIds = false,
    ): array {
        $companyIds = $this->accessibleCompanyIds($owner);

        if ($establishmentId !== null) {
            $establishmentCompanyId = Establishment::query()->whereKey($establishmentId)->value('company_id');

            if ($establishmentCompanyId !== null) {
                $companyIds = [(int) $establishmentCompanyId];
            }
        }

        $includeIds = array_values(array_unique(array_filter(
            array_map('intval', $includeIds),
            fn (int $id): bool => $id > 0,
        )));

        if ($onlyIncludeIds) {
            if ($includeIds === []) {
                return [];
            }

            return Requester::query()
                ->whereIn('id', $includeIds)
                ->orderBy('name')
                ->get(['id', 'name', 'company_id'])
                ->map(fn (Requester $requester): array => [
                    'id' => $requester->id,
                    'label' => $requester->name,
                    'company_id' => (int) $requester->company_id,
                ])
                ->values()
                ->all();
        }

        if ($companyIds === []) {
            return [];
        }

        $needle = trim((string) $search);

        $rows = Requester::query()
            ->whereIn('company_id', $companyIds)
            ->when($needle !== '', fn ($query) => $query->where('name', 'like', "%{$needle}%"))
            ->orderBy('name')
            ->when($limit !== null, fn ($query) => $query->limit($limit))
            ->get(['id', 'name', 'company_id'])
            ->map(fn (Requester $requester): array => [
                'id' => $requester->id,
                'label' => $requester->name,
                'company_id' => (int) $requester->company_id,
            ])
            ->values()
            ->all();

        if ($includeIds !== []) {
            $missingIds = array_values(array_diff(
                $includeIds,
                array_map(fn (array $row): int => (int) $row['id'], $rows),
            ));

            if ($missingIds !== []) {
                $extra = Requester::query()
                    ->whereIn('id', $missingIds)
                    ->orderBy('name')
                    ->get(['id', 'name', 'company_id'])
                    ->map(fn (Requester $requester): array => [
                        'id' => $requester->id,
                        'label' => $requester->name,
                        'company_id' => (int) $requester->company_id,
                    ])
                    ->all();

                $rows = array_values(array_merge($extra, $rows));
            }
        }

        return $rows;
    }

    /**
     * Seed options for Inertia pages (selected technicians only). Full lists load via /select-options/technicians.
     *
     * @param  list<int>  $includeIds
     * @return list<array{id: int, label: string, logo_url: string|null}>
     */
    public function technicianOptions(Company $owner, array $includeIds = []): array
    {
        return app(EstablishmentService::class)->technicianOptions($owner, $includeIds);
    }

    /**
     * Billing-line articles for a document, scoped like legacy articulo_cliente:
     * articles linked to the customer relationship of the establishment's client.
     *
     * Sale price comes from article_clients; DL/DEL/ML/MEL are overridden by client_rates
     * (tarifas) for the selected priority + work-order type, with P5 / Bajo Impacto fallback.
     *
     * @param  list<int>  $includeArticleIds
     * @return list<array{id: int, label: string, code: string, description: string|null, unit_price: string}>
     */
    public function articleOptions(
        Company $owner,
        ?int $establishmentId = null,
        ?int $clientPriorityId = null,
        ?int $workOrderTypeId = null,
        array $includeArticleIds = [],
        ?string $search = null,
        ?int $limit = null,
    ): array {
        if ($establishmentId === null || $establishmentId <= 0) {
            return $this->articleOptionsForIds($includeArticleIds);
        }

        $relationship = $this->customerRelationshipForEstablishment($owner, $establishmentId);

        if ($relationship === null) {
            return $this->articleOptionsForIds($includeArticleIds);
        }

        $needle = trim((string) $search);

        $rows = ArticleClient::query()
            ->with(['article.languages'])
            ->where('company_relationship_id', $relationship->id)
            ->when($needle !== '', function ($query) use ($needle): void {
                $query->whereHas('article', function ($articles) use ($needle): void {
                    $articles->where('code', 'like', "%{$needle}%")
                        ->orWhereHas('languages', fn ($languages) => $languages->where('name', 'like', "%{$needle}%"));
                });
            })
            ->orderBy('article_id')
            ->when($limit !== null, fn ($query) => $query->limit($limit))
            ->get();

        $rate = $this->clientRateFor($relationship->id, $clientPriorityId, $workOrderTypeId);
        $fallbackRate = $this->fallbackClientRateFor($relationship->id, $workOrderTypeId, $rate);

        $options = $rows
            ->filter(fn (ArticleClient $row): bool => $row->article !== null)
            ->map(function (ArticleClient $row) use ($rate, $fallbackRate): array {
                /** @var Article $article */
                $article = $row->article;
                $translation = $article->languages->firstWhere('language_id', 1)
                    ?? $article->languages->sortBy('language_id')->first();
                $name = $translation?->name;
                $code = $article->code;
                $salePrice = (string) $row->sale_price;

                return [
                    'id' => $article->id,
                    'label' => $name ? "{$code} — {$name}" : $code,
                    'code' => $code,
                    'description' => $translation?->description,
                    'unit_price' => $this->unitPriceForArticleCode($code, $salePrice, $rate, $fallbackRate),
                ];
            })
            ->keyBy('id');

        foreach ($this->articleOptionsForIds($includeArticleIds) as $extra) {
            if (! $options->has($extra['id'])) {
                $options->put($extra['id'], $extra);
            }
        }

        return $options->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
    }

    /**
     * @param  list<int>  $articleIds
     * @return list<array{id: int, label: string, code: string, description: string|null, unit_price: string}>
     */
    private function articleOptionsForIds(array $articleIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $articleIds), fn (int $id): bool => $id > 0)));

        if ($ids === []) {
            return [];
        }

        return Article::query()
            ->with('languages')
            ->whereIn('id', $ids)
            ->orderBy('code')
            ->get()
            ->map(function (Article $article): array {
                $translation = $article->languages->firstWhere('language_id', 1)
                    ?? $article->languages->sortBy('language_id')->first();
                $name = $translation?->name;
                $code = $article->code;

                return [
                    'id' => $article->id,
                    'label' => $name ? "{$code} — {$name}" : $code,
                    'code' => $code,
                    'description' => $translation?->description,
                    'unit_price' => '0',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Read-only client rate rows for the estimate Tarifas tab (legacy presupuestos tarifas).
     *
     * @return array{data: list<array<string, mixed>>, client_name: string|null}
     */
    public function clientRatesForEstimate(Company $owner, int $establishmentId, int $workOrderTypeId): array
    {
        $relationship = $this->customerRelationshipForEstablishment($owner, $establishmentId);

        if ($relationship === null) {
            return ['data' => [], 'client_name' => null];
        }

        $relationship->loadMissing('relatedCompany:id,name,tradename');

        $rows = ClientRate::query()
            ->with(['clientPriority:id,name,code', 'workOrderType:id,name,code'])
            ->where('company_relationship_id', $relationship->id)
            ->where('work_order_type_id', $workOrderTypeId)
            ->orderBy('client_priority_id')
            ->get()
            ->map(fn (ClientRate $rate): array => [
                'id' => $rate->id,
                'client_priority_id' => (int) $rate->client_priority_id,
                'client_priority_label' => $rate->clientPriority?->name ?? '#'.$rate->client_priority_id,
                'work_order_type_id' => (int) $rate->work_order_type_id,
                'work_order_type_label' => $rate->workOrderType?->name ?? '#'.$rate->work_order_type_id,
                'travel_amount' => (string) $rate->travel_amount,
                'extra_travel_amount' => (string) $rate->extra_travel_amount,
                'labor_amount' => (string) $rate->labor_amount,
                'extra_labor_amount' => (string) $rate->extra_labor_amount,
                'is_urgent' => (bool) $rate->is_urgent,
            ])
            ->values()
            ->all();

        $clientName = $relationship->relatedCompany?->tradename
            ?: $relationship->relatedCompany?->name;

        return [
            'data' => $rows,
            'client_name' => $clientName,
        ];
    }

    private function customerRelationshipForEstablishment(Company $owner, int $establishmentId): ?CompanyRelationship
    {
        $clientCompanyId = Establishment::query()->whereKey($establishmentId)->value('company_id');

        if ($clientCompanyId === null) {
            return null;
        }

        return CompanyRelationship::query()
            ->where('owner_company_id', $owner->id)
            ->where('related_company_id', $clientCompanyId)
            ->where('kind', CompanyRelationshipKind::Customer->value)
            ->first();
    }

    private function clientRateFor(
        int $relationshipId,
        ?int $clientPriorityId,
        ?int $workOrderTypeId,
    ): ?ClientRate {
        if ($clientPriorityId === null || $clientPriorityId <= 0 || $workOrderTypeId === null || $workOrderTypeId <= 0) {
            return null;
        }

        return ClientRate::query()
            ->where('company_relationship_id', $relationshipId)
            ->where('client_priority_id', $clientPriorityId)
            ->where('work_order_type_id', $workOrderTypeId)
            ->first();
    }

    /**
     * Legacy fallback: tarifas for Codigo Verde / Bajo Impacto (clave P5).
     */
    private function fallbackClientRateFor(int $relationshipId, ?int $workOrderTypeId, ?ClientRate $primary): ?ClientRate
    {
        if ($workOrderTypeId === null || $workOrderTypeId <= 0) {
            return null;
        }

        $greenPriorityId = ClientPriority::query()
            ->where(function ($query): void {
                $query->where('code', 'P5')
                    ->orWhere('name', 'like', 'Bajo Impacto%')
                    ->orWhere('name', 'like', 'Código Verde%')
                    ->orWhere('name', 'like', 'Codigo Verde%');
            })
            ->value('id');

        if ($greenPriorityId === null) {
            return null;
        }

        if ($primary !== null && (int) $primary->client_priority_id === (int) $greenPriorityId) {
            return null;
        }

        return ClientRate::query()
            ->where('company_relationship_id', $relationshipId)
            ->where('client_priority_id', $greenPriorityId)
            ->where('work_order_type_id', $workOrderTypeId)
            ->first();
    }

    private function unitPriceForArticleCode(
        string $code,
        string $salePrice,
        ?ClientRate $rate,
        ?ClientRate $fallback,
    ): string {
        $resolved = match (strtoupper($code)) {
            'DL' => $rate?->travel_amount ?? $fallback?->travel_amount,
            'DEL' => $rate?->extra_travel_amount ?? $fallback?->extra_travel_amount,
            'ML' => $rate?->labor_amount ?? $fallback?->labor_amount,
            'MEL' => $rate?->extra_labor_amount ?? $fallback?->extra_labor_amount,
            default => null,
        };

        if ($resolved === null || $resolved === '') {
            return $salePrice;
        }

        return (string) $resolved;
    }

    public function defaultStatusId(WorkOrderStage $stage): ?int
    {
        return $this->statuses->defaultId($stage);
    }

    /**
     * @param  array{search?: string|null, stage?: string|null, is_estimate?: bool|null, is_work_order?: bool|null, pending?: string|null, establishment_id?: int|string|null, contract_id?: int|string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null, created_from?: string|null, created_to?: string|null}  $filters
     * @return LengthAwarePaginator<int, WorkOrder>
     */
    public function paginateForOwner(Company $owner, array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort(
            $filters,
            ['id', 'code', 'subject', 'stage', 'created_at'],
            'id',
        );

        $query = $this->filteredQueryForOwner($owner, $filters)
            ->with([
                'establishment:id,name',
                'status:id,name,color',
                'responsibleUser:id,name',
                'type:id,name,color',
                'priority:id,name,color',
                // List amounts use denormalized header cols; only need selected tech for display name.
                'technicians' => fn ($query) => $query->where('is_selected', true),
                'technicians.technician:id,related_company_id',
                'technicians.technician.relatedCompany:id,name,tradename',
            ])
            ->orderBy($sort, $direction);

        $search = trim((string) ($filters['search'] ?? ''));

        // Leading-wildcard LIKE cannot use indexes; COUNT(*) over ~80k+ matches takes seconds.
        // For search, skip the exact total and only detect whether another page exists.
        if ($search !== '') {
            $page = max(1, (int) ($filters['page'] ?? LengthAwarePaginatorConcrete::resolveCurrentPage()));
            $rows = (clone $query)
                ->forPage($page, $perPage + 1)
                ->get();
            $hasMore = $rows->count() > $perPage;
            $items = $rows->take($perPage)->values();
            $total = (($page - 1) * $perPage) + $items->count() + ($hasMore ? 1 : 0);

            return (new LengthAwarePaginatorConcrete(
                $items,
                $total,
                $perPage,
                $page,
                [
                    'path' => LengthAwarePaginatorConcrete::resolveCurrentPath(),
                    'pageName' => 'page',
                ],
            ))->withQueryString();
        }

        return $query
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Aggregate totals for the active list filters (legacy presupuestos indexTotales).
     * Uses denormalized header columns (kept in sync on save) so index lists stay fast.
     *
     * @param  array{search?: string|null, stage?: string|null, is_estimate?: bool|null, is_work_order?: bool|null, pending?: string|null, establishment_id?: int|string|null, contract_id?: int|string|null, created_from?: string|null, created_to?: string|null}  $filters
     * @return array{count: int, total_amount: float, cost_amount: float, margin_percentage: float}|null
     */
    public function totalsForOwner(Company $owner, array $filters = []): ?array
    {
        // Same reason as paginateForOwner: LIKE '%…%' aggregates are multi-second scans.
        if (trim((string) ($filters['search'] ?? '')) !== '') {
            return null;
        }

        $row = $this->filteredQueryForOwner($owner, $filters)
            ->selectRaw('COUNT(*) as quantity')
            ->selectRaw('COALESCE(SUM(COALESCE(total_euros, total_amount, 0)), 0) as total_amount')
            ->selectRaw('COALESCE(SUM(COALESCE(cost_amount, 0)), 0) as cost_amount')
            ->first();

        return $this->formatTotalsRow($row);
    }

    /**
     * @param  array{search?: string|null, stage?: string|null, is_estimate?: bool|null, is_work_order?: bool|null, pending?: string|null, establishment_id?: int|string|null, contract_id?: int|string|null, created_from?: string|null, created_to?: string|null}  $filters
     * @return Builder<WorkOrder>
     */
    private function filteredQueryForOwner(Company $owner, array $filters = [])
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $stage = trim((string) ($filters['stage'] ?? ''));
        $pending = trim((string) ($filters['pending'] ?? ''));
        $establishmentId = (int) ($filters['establishment_id'] ?? 0);
        $contractId = (int) ($filters['contract_id'] ?? 0);
        $createdFrom = trim((string) ($filters['created_from'] ?? ''));
        $createdTo = trim((string) ($filters['created_to'] ?? ''));
        $isEstimate = array_key_exists('is_estimate', $filters) ? $filters['is_estimate'] : null;
        $isWorkOrder = array_key_exists('is_work_order', $filters) ? $filters['is_work_order'] : null;

        return WorkOrder::query()
            // All rows are owned; avoid OR + whereHas(establishment IN thousands) which blocks indexes.
            ->where('owner_company_id', $owner->id)
            ->tap(fn (Builder $query) => $this->applyOwnerListIndexHint($query, $filters))
            ->when($establishmentId > 0, function ($query) use ($establishmentId): void {
                $query->where('establishment_id', $establishmentId);
            })
            ->when($contractId > 0, function ($query) use ($contractId): void {
                $query->where('contract_id', $contractId);
            })
            ->when($isEstimate !== null, function ($query) use ($isEstimate): void {
                $query->where('is_estimate', (bool) $isEstimate);
            })
            ->when($isWorkOrder !== null, function ($query) use ($isWorkOrder): void {
                $query->where('is_work_order', (bool) $isWorkOrder);
            })
            ->when($stage !== '' && in_array($stage, WorkOrderStage::values(), true), function ($query) use ($stage): void {
                $query->where('stage', $stage);
            })
            ->when($pending === '1' || $pending === '0', function ($query) use ($pending): void {
                $statusIds = $this->statuses->idsByOpen($pending === '1');
                $query->whereIn('status_id', $statusIds !== [] ? $statusIds : [0]);
            })
            ->when($search !== '', function ($query) use ($search): void {
                $this->applyOwnerListSearch($query, $search);
            })
            ->when($createdFrom !== '', fn ($query) => $query->whereDate('created_at', '>=', $createdFrom))
            ->when($createdTo !== '', fn ($query) => $query->whereDate('created_at', '<=', $createdTo));
    }

    /**
     * Fast list search for ~900k work_orders rows.
     *
     * - Code-like tokens (OT26/224606, PRE26/…): indexed prefix/exact on number columns.
     * - Digits only: match the numeric tail after "/" (functional index when present).
     * - Free text: subject/reference + establishment name (avoid OR-ing every code column with %…%).
     *
     * @param  Builder<WorkOrder>  $query
     */
    private function applyOwnerListSearch(Builder $query, string $search): void
    {
        $like = '%'.$search.'%';
        $prefix = $search.'%';
        $digitsOnly = (bool) preg_match('/\A\d{3,}\z/', $search);
        $codeLike = ! $digitsOnly && (bool) preg_match('/\A[A-Za-z0-9][A-Za-z0-9\/\-_.]*\z/', $search)
            && (
                str_contains($search, '/')
                || str_contains($search, '-')
                || (bool) preg_match('/\A[A-Za-z]{1,8}\d/', $search)
            );

        $establishmentIds = Establishment::query()
            ->where(function ($establishmentQuery) use ($like): void {
                $establishmentQuery
                    ->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like);
            })
            ->limit(500)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $query->where(function ($inner) use ($search, $like, $prefix, $digitsOnly, $codeLike, $establishmentIds): void {
            if ($digitsOnly) {
                // Only columns covered by functional tail indexes — extra ORs kill index_merge.
                $inner
                    ->whereRaw("SUBSTRING_INDEX(code, _utf8mb4'/', -1) = ?", [$search])
                    ->orWhereRaw("SUBSTRING_INDEX(work_order_num, _utf8mb4'/', -1) = ?", [$search])
                    ->orWhereRaw("SUBSTRING_INDEX(estimate_num, _utf8mb4'/', -1) = ?", [$search]);
            } elseif ($codeLike) {
                $inner
                    ->where('code', $search)
                    ->orWhere('code', 'like', $prefix)
                    ->orWhere('estimate_num', $search)
                    ->orWhere('estimate_num', 'like', $prefix)
                    ->orWhere('work_order_num', $search)
                    ->orWhere('work_order_num', 'like', $prefix);
            } else {
                // Newest-first LIMIT fills quickly when the term is common in recent rows.
                // FULLTEXT is slower here because it ranks the whole table before status filters.
                $inner
                    ->where('subject', 'like', $like)
                    ->orWhere('reference', 'like', $like);

                if ($establishmentIds !== []) {
                    $inner->orWhereIn('establishment_id', $establishmentIds);
                }
            }

            if (($digitsOnly || $codeLike) && $establishmentIds !== []) {
                $inner->orWhereIn('establishment_id', $establishmentIds);
            }
        });
    }

    /**
     * Default index lists filter owner + is_work_order|is_estimate + status IN (…).
     * MySQL often prefers a PRIMARY backward scan for ORDER BY id DESC LIMIT n, which is
     * fine when many rows match (work orders) but catastrophic when few do (estimates).
     * Force the covering owner-list indexes for the common unscoped list.
     *
     * @param  Builder<WorkOrder>  $query
     * @param  array{search?: string|null, is_estimate?: bool|null, is_work_order?: bool|null, establishment_id?: int|string|null, contract_id?: int|string|null, created_from?: string|null, created_to?: string|null, pending?: string|null}  $filters
     */
    private function applyOwnerListIndexHint(Builder $query, array $filters): void
    {
        if (
            trim((string) ($filters['search'] ?? '')) !== ''
            || trim((string) ($filters['created_from'] ?? '')) !== ''
            || trim((string) ($filters['created_to'] ?? '')) !== ''
            || (int) ($filters['establishment_id'] ?? 0) > 0
            || (int) ($filters['contract_id'] ?? 0) > 0
        ) {
            return;
        }

        $pending = trim((string) ($filters['pending'] ?? ''));

        // Only force when status is filtered; "all" has no status_id predicate.
        if ($pending !== '1' && $pending !== '0') {
            return;
        }

        $isEstimate = array_key_exists('is_estimate', $filters) ? $filters['is_estimate'] : null;
        $isWorkOrder = array_key_exists('is_work_order', $filters) ? $filters['is_work_order'] : null;

        $index = match (true) {
            // Estimates: PRIMARY backward scan skips most rows and is very slow; force covering index.
            $isEstimate === true && $isWorkOrder !== true => 'work_orders_owner_est_list_idx',
            default => null,
        };

        if ($index === null) {
            return;
        }

        $query->from(DB::raw('`work_orders` FORCE INDEX (`'.$index.'`)'));
    }

    /**
     * @param  array{search?: string|null, stage?: string|null, pending?: string|null, establishment_id?: int|string|null, contract_id?: int|string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null, created_from?: string|null, created_to?: string|null}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateForWeb(Company $owner, array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        return $this->paginateForOwner($owner, $filters, $perPage)
            ->through(fn (WorkOrder $workOrder): array => $this->toListItem($workOrder));
    }

    /**
     * Aggregate totals for an establishment (legacy `components.totales` for ots/presupuestos).
     *
     * @return array{count: int, total_amount: float, cost_amount: float, margin_percentage: float}
     */
    public function totalsForEstablishment(Company $owner, int $establishmentId, WorkOrderStage $stage): array
    {
        $companyIds = $this->accessibleCompanyIds($owner);

        $row = WorkOrder::query()
            ->where('establishment_id', $establishmentId)
            ->when(
                $stage === WorkOrderStage::Estimate,
                fn ($query) => $query->where('is_estimate', true),
                fn ($query) => $query->where('is_work_order', true),
            )
            ->where(function ($query) use ($owner, $companyIds): void {
                $query->where('owner_company_id', $owner->id)
                    ->orWhere(function ($legacy) use ($companyIds): void {
                        $legacy->whereNull('owner_company_id')
                            ->whereHas('establishment', function ($establishment) use ($companyIds): void {
                                $establishment->whereIn('company_id', $companyIds);
                            });
                    });
            })
            ->selectRaw('COUNT(*) as quantity')
            ->selectRaw('COALESCE(SUM(COALESCE(total_euros, total_amount, 0)), 0) as total_amount')
            ->selectRaw('COALESCE(SUM(COALESCE(cost_amount, 0)), 0) as cost_amount')
            ->first();

        return $this->formatTotalsRow($row);
    }

    /**
     * Aggregate totals for work orders linked to a contract.
     *
     * @return array{count: int, total_amount: float, cost_amount: float, margin_percentage: float}
     */
    public function totalsForContract(Company $owner, int $contractId, WorkOrderStage $stage): array
    {
        $companyIds = $this->accessibleCompanyIds($owner);

        $row = WorkOrder::query()
            ->where('contract_id', $contractId)
            ->when(
                $stage === WorkOrderStage::Estimate,
                fn ($query) => $query->where('is_estimate', true),
                fn ($query) => $query->where('is_work_order', true),
            )
            ->where(function ($query) use ($owner, $companyIds): void {
                $query->where('owner_company_id', $owner->id)
                    ->orWhere(function ($legacy) use ($companyIds): void {
                        $legacy->whereNull('owner_company_id')
                            ->whereHas('establishment', function ($establishment) use ($companyIds): void {
                                $establishment->whereIn('company_id', $companyIds);
                            });
                    });
            })
            ->selectRaw('COUNT(*) as quantity')
            ->selectRaw('COALESCE(SUM(COALESCE(total_euros, total_amount, 0)), 0) as total_amount')
            ->selectRaw('COALESCE(SUM(COALESCE(cost_amount, 0)), 0) as cost_amount')
            ->first();

        return $this->formatTotalsRow($row);
    }

    /**
     * @param  object{quantity?: mixed, total_amount?: mixed, cost_amount?: mixed}|null  $row
     * @return array{count: int, total_amount: float, cost_amount: float, margin_percentage: float}
     */
    private function formatTotalsRow(?object $row): array
    {
        $total = round((float) ($row?->total_amount ?? 0), 2);
        $cost = round((float) ($row?->cost_amount ?? 0), 2);

        if ($total === 0.0 && $cost !== 0.0) {
            $margin = -100.0;
        } elseif ($total === 0.0 && $cost === 0.0) {
            $margin = 0.0;
        } else {
            $margin = round((1 - ($cost / $total)) * 100, 2);
            $margin = max(-100.0, min(100.0, $margin));
        }

        return [
            'count' => (int) ($row?->quantity ?? 0),
            'total_amount' => $total,
            'cost_amount' => $cost,
            'margin_percentage' => $margin,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Company $owner, array $data): WorkOrder
    {
        return DB::transaction(function () use ($owner, $data): WorkOrder {
            $stage = WorkOrderStage::from((string) $data['stage']);

            if ($stage === WorkOrderStage::Estimate) {
                if (! filled($data['status_id'] ?? null)) {
                    $data['status_id'] = $this->defaultStatusId($stage);
                }

                if (! filled($data['due_at'] ?? null)) {
                    $data['due_at'] = now()->addDay()->format('Y-m-d\TH:i');
                }
            }

            // optima_back: establecimiento → delegacion → moneda (sets both on the document)
            if (filled($data['establishment_id'] ?? null)) {
                $fromEstablishment = $this->delegationAndCurrencyFromEstablishment((int) $data['establishment_id']);
                $data['delegation_id'] = $fromEstablishment['delegation_id'];
                $data['currency_id'] = $fromEstablishment['currency_id'];
            }

            $attributes = $this->attributes($data, $stage);
            $attributes['owner_company_id'] = $owner->id;
            $attributes['is_estimate'] = $stage === WorkOrderStage::Estimate;
            $attributes['is_work_order'] = $stage === WorkOrderStage::WorkOrder;

            // Always allocate when the pattern is missing/active (ignore client peek of suggestedCode).
            $allocation = $this->allocateNumbering($owner, $stage);

            if ($allocation !== null) {
                $attributes = [
                    ...$attributes,
                    ...$this->numberingAttributesForStage($stage, $allocation),
                ];
            } elseif (filled($data['code'] ?? null)) {
                $attributes = [
                    ...$attributes,
                    ...$this->manualNumberingAttributesForStage($stage, (string) $data['code']),
                ];
            }

            if (($attributes['billing_company_id'] ?? null) === null) {
                $attributes['billing_company_id'] = Establishment::query()
                    ->whereKey($attributes['establishment_id'])
                    ->value('company_id');
            }

            if ($stage === WorkOrderStage::WorkOrder) {
                $attributes['confirmed_at'] = now();
            }

            $workOrder = WorkOrder::query()->create($attributes);

            // optima_back: if no trabajos_a_realizar, seed from asunto (split by +)
            if (($data['tasks'] ?? []) === [] && filled($attributes['subject'] ?? null)) {
                $subject = (string) $attributes['subject'];
                $parts = array_values(array_filter(array_map('trim', explode('+', $subject)), fn (string $part): bool => $part !== ''));

                if ($parts === []) {
                    $parts = [$subject];
                }

                $data['tasks'] = array_map(
                    static fn (string $part): array => [
                        'title' => $part,
                        'description' => $part,
                        'is_completed' => false,
                    ],
                    $parts,
                );
            }

            $this->syncChildren($workOrder, $data);
            $this->refreshHeaderTotals($workOrder);

            $createdStatusId = $workOrder->status_id !== null ? (int) $workOrder->status_id : null;
            $this->statusChanges->record(
                ChatDocumentType::WorkOrder,
                (int) $workOrder->id,
                null,
                $createdStatusId,
            );

            if ($this->statuses->statusConfirmsEstimate($workOrder->status_id !== null ? (int) $workOrder->status_id : null) && $workOrder->isEstimate()) {
                $this->confirmation->confirm($workOrder, $this->statuses->postConfirmDefaultId(), $owner);
                $confirmed = $workOrder->fresh() ?? $workOrder;
                $this->statusChanges->record(
                    ChatDocumentType::WorkOrder,
                    (int) $confirmed->id,
                    $createdStatusId,
                    $confirmed->status_id !== null ? (int) $confirmed->status_id : null,
                );
            }

            $fresh = $workOrder->fresh($this->defaultRelations()) ?? $workOrder;
            $this->applyStatusSideEffects($fresh, $createdStatusId ?? 0, true);
            $fresh = $fresh->fresh($this->defaultRelations()) ?? $fresh;
            $this->qualityScores->handleEstimateSent($fresh, null);

            return $fresh;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{work_order: WorkOrder, cloned_estimate: WorkOrder|null}
     */
    public function update(Company $owner, WorkOrder $workOrder, array $data, ?User $actor = null): array
    {
        return DB::transaction(function () use ($owner, $workOrder, $data, $actor): array {
            $workOrder->loadMissing('status');
            $newStatusId = (int) $data['status_id'];
            $oldStatusId = (int) $workOrder->status_id;
            $previousSentAt = $workOrder->sent_at?->toDateTimeString();
            $wasOpen = (bool) ($workOrder->status?->is_open ?? true);
            $fieldsLocked = ! $wasOpen && ! $this->actorCanUpdateClosed($actor, $workOrder);
            $approving = $workOrder->isEstimate() && $this->statuses->statusConfirmsEstimate($newStatusId);
            $rejecting = $workOrder->isConfirmedWorkOrder() && $this->statuses->statusRejectsToEstimate($newStatusId);

            if ($newStatusId !== $oldStatusId && ! $this->statuses->canTransition($oldStatusId, $newStatusId)) {
                throw ValidationException::withMessages([
                    'status_id' => 'This status change is not allowed.',
                ]);
            }

            $justification = trim((string) ($data['status_justification'] ?? ''));

            if ($newStatusId !== $oldStatusId) {
                $edge = $this->statuses->transition($oldStatusId, $newStatusId);

                if ($edge?->requires_justification && mb_strlen($justification) < 10) {
                    throw ValidationException::withMessages([
                        'status_justification' => 'A justification of at least 10 characters is required.',
                    ]);
                }
            }

            if ($fieldsLocked) {
                $this->assertClosedFieldsUnchanged($workOrder, $data);
            }

            // Prod Facturada/Abonada (ciclo_vida >= 8): almost no header updates; SLA only with update-closed.
            $currentLifecycle = $workOrder->status?->lifecycle !== null ? (int) $workOrder->status->lifecycle : null;
            $isInvoicedLike = $currentLifecycle !== null && $currentLifecycle >= 8;

            if ($isInvoicedLike && $newStatusId === $oldStatusId) {
                $slaPayload = [];

                if (
                    array_key_exists('sla_at', $data)
                    && $this->actorCanUpdateClosed($actor, $workOrder)
                ) {
                    $slaPayload['sla_at'] = $data['sla_at'] ?: null;
                    $slaPayload['sla_justification'] = $data['sla_justification'] ?? $workOrder->sla_justification;
                }

                if ($slaPayload !== []) {
                    $workOrder->update($slaPayload);
                }

                return [
                    'work_order' => $workOrder->fresh($this->defaultRelations()) ?? $workOrder,
                    'cloned_estimate' => null,
                ];
            }

            if ($workOrder->isConfirmedWorkOrder() || $workOrder->stage === WorkOrderStage::WorkOrder) {
                $this->assertWorkOrderBusinessRules($workOrder, $data, $oldStatusId, $newStatusId, $actor);
            }

            $attributes = $this->attributes($data, $workOrder->stage);

            // Prod nonUpdatableFields: received_at is not client-writable without update-closed.
            if (! $this->actorCanUpdateClosed($actor, $workOrder)) {
                unset($attributes['received_at']);
            }

            if (! $fieldsLocked) {
                $attributes = [
                    ...$attributes,
                    ...$this->syncNumberingFromCodeAttributes($workOrder, $data),
                ];
            }

            if ($approving || $rejecting) {
                unset($attributes['status_id']);
            }

            if ($fieldsLocked) {
                // Prod (UpdateOtUseCase): closed without update-closed may still change status, referencia, po.
                $attributes = array_intersect_key($attributes, [
                    'status_id' => true,
                    'reference' => true,
                    'purchase_order' => true,
                ]);
            }

            if ($attributes !== []) {
                $workOrder->update($attributes);
            }

            if (! $fieldsLocked) {
                $this->syncChildren($workOrder, $data);
                $this->syncChecklistCompletions($workOrder, $data, $actor);
                $this->refreshHeaderTotals($workOrder);
            }

            $clone = null;

            if ($approving) {
                $this->confirmation->confirm(
                    $workOrder->fresh() ?? $workOrder,
                    $this->statuses->postConfirmDefaultId(),
                    $owner,
                );
            } elseif ($rejecting) {
                $clone = $this->rejectToEstimate($owner, $workOrder->fresh() ?? $workOrder);
            } elseif ($newStatusId !== $oldStatusId) {
                $this->assertStatusMatchesStage($workOrder->stage, $newStatusId);
                $workOrder->forceFill(['status_id' => $newStatusId])->save();
            }

            $fresh = $workOrder->fresh($this->defaultRelations()) ?? $workOrder;
            $this->applyStatusSideEffects($fresh, $oldStatusId, $wasOpen);
            $fresh = $fresh->fresh($this->defaultRelations()) ?? $fresh;

            $finalStatusId = $fresh->status_id !== null ? (int) $fresh->status_id : null;
            $this->statusChanges->record(
                ChatDocumentType::WorkOrder,
                (int) $fresh->id,
                $oldStatusId,
                $finalStatusId,
                $actor,
                $justification !== '' ? $justification : null,
            );

            if ($clone !== null) {
                $this->statusChanges->record(
                    ChatDocumentType::WorkOrder,
                    (int) $clone->id,
                    null,
                    $clone->status_id !== null ? (int) $clone->status_id : null,
                    $actor,
                );
            }

            $this->qualityScores->handleEstimateSent($fresh, $previousSentAt);

            $isOpen = (bool) ($fresh->status?->is_open ?? true);
            $this->qualityScores->handleWorkOrderClosed($fresh, $wasOpen, $isOpen);

            return [
                'work_order' => $fresh,
                'cloned_estimate' => $clone,
            ];
        });
    }

    /**
     * Status-only update (index bulk actions / inline status change).
     */
    public function changeStatus(
        Company $owner,
        WorkOrder $workOrder,
        int $newStatusId,
        ?User $actor = null,
        ?string $justification = null,
    ): WorkOrder {
        return DB::transaction(function () use ($owner, $workOrder, $newStatusId, $actor, $justification): WorkOrder {
            $workOrder->loadMissing('status');
            $oldStatusId = (int) $workOrder->status_id;
            $previousSentAt = $workOrder->sent_at?->toDateTimeString();
            $wasOpen = (bool) ($workOrder->status?->is_open ?? true);
            $fieldsLocked = ! $wasOpen && ! $this->actorCanUpdateClosed($actor, $workOrder);
            $approving = $workOrder->isEstimate() && $this->statuses->statusConfirmsEstimate($newStatusId);
            $rejecting = $workOrder->isConfirmedWorkOrder() && $this->statuses->statusRejectsToEstimate($newStatusId);

            if ($fieldsLocked && $newStatusId === $oldStatusId) {
                return $workOrder;
            }

            if ($newStatusId !== $oldStatusId && ! $this->statuses->canTransition($oldStatusId, $newStatusId)) {
                throw ValidationException::withMessages([
                    'status_id' => 'This status change is not allowed.',
                ]);
            }

            $comment = trim((string) $justification);

            if ($newStatusId !== $oldStatusId) {
                $edge = $this->statuses->transition($oldStatusId, $newStatusId);

                if ($edge?->requires_justification && mb_strlen($comment) < 10) {
                    throw ValidationException::withMessages([
                        'status_justification' => 'A justification of at least 10 characters is required.',
                    ]);
                }
            }

            if ($approving) {
                $this->confirmation->confirm(
                    $workOrder,
                    $this->statuses->postConfirmDefaultId(),
                    $owner,
                );
            } elseif ($rejecting) {
                $this->rejectToEstimate($owner, $workOrder);
            } elseif ($newStatusId !== $oldStatusId) {
                $this->assertStatusMatchesStage($workOrder->stage, $newStatusId);
                $workOrder->forceFill(['status_id' => $newStatusId])->save();
            }

            $fresh = $workOrder->fresh($this->defaultRelations()) ?? $workOrder;
            $this->applyStatusSideEffects($fresh, $oldStatusId, $wasOpen);
            $fresh = $fresh->fresh($this->defaultRelations()) ?? $fresh;

            $this->statusChanges->record(
                ChatDocumentType::WorkOrder,
                (int) $fresh->id,
                $oldStatusId,
                $fresh->status_id !== null ? (int) $fresh->status_id : null,
                $actor,
                $comment !== '' ? $comment : null,
            );

            $this->qualityScores->handleEstimateSent($fresh, $previousSentAt);

            $isOpen = (bool) ($fresh->status?->is_open ?? true);
            $this->qualityScores->handleWorkOrderClosed($fresh, $wasOpen, $isOpen);

            return $fresh;
        });
    }

    /**
     * @param  list<int>  $ids
     * @return array{updated: int, failed: list<array{id: int, message: string}>}
     */
    public function bulkChangeStatus(
        Company $owner,
        array $ids,
        int $statusId,
        WorkOrderStage $stage,
        ?User $actor = null,
        ?string $justification = null,
    ): array {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $ids),
            fn (int $id): bool => $id > 0,
        )));

        $updated = 0;
        $failed = [];

        $documents = $this->filteredQueryForOwner($owner, $stage === WorkOrderStage::Estimate
            ? ['is_estimate' => true, 'stage' => WorkOrderStage::Estimate->value]
            : ['is_work_order' => true, 'stage' => WorkOrderStage::WorkOrder->value])
            ->whereIn('work_orders.id', $ids)
            ->get()
            ->keyBy('id');

        foreach ($ids as $id) {
            $document = $documents->get($id);

            if (! $document instanceof WorkOrder) {
                $failed[] = ['id' => $id, 'message' => 'Document not found.'];

                continue;
            }

            try {
                if ($actor !== null && ! app(EstimatePolicy::class)->update($actor, $document)) {
                    $failed[] = ['id' => $id, 'message' => 'Not authorized.'];

                    continue;
                }

                $this->changeStatus($owner, $document, $statusId, $actor, $justification);
                $updated++;
            } catch (ValidationException $exception) {
                $messages = $exception->errors();
                $first = collect($messages)->flatten()->first();
                $failed[] = [
                    'id' => $id,
                    'message' => is_string($first) ? $first : 'Status change failed.',
                ];
            }
        }

        return [
            'updated' => $updated,
            'failed' => $failed,
        ];
    }

    public function delete(WorkOrder $workOrder): void
    {
        if ($workOrder->trashed()) {
            return;
        }

        $workOrder->delete();
    }

    public function rejectToEstimate(Company $owner, WorkOrder $workOrder): WorkOrder
    {
        if (! $workOrder->isConfirmedWorkOrder()) {
            throw ValidationException::withMessages([
                'status_id' => 'Only a work order can be rejected back to an estimate.',
            ]);
        }

        $estimateStatusId = $this->defaultStatusId(WorkOrderStage::Estimate);
        $rejectedStatusId = $this->statuses->rejectsToEstimateId();

        if ($estimateStatusId === null || $rejectedStatusId === null) {
            throw ValidationException::withMessages([
                'status_id' => 'Configure default and reject-to-estimate statuses first.',
            ]);
        }

        $workOrder->forceFill([
            'status_id' => $rejectedStatusId,
        ])->save();

        $clone = $workOrder->replicate([
            'code',
            'stage',
            'confirmed_at',
            'status_id',
            'source_work_order_id',
            'closed_at',
            'billed_at',
            'is_estimate',
            'is_work_order',
            'estimate_num',
            'estimate_num_cardinal',
            'estimate_numbering_pattern_id',
            'estimate_old_num',
            'work_order_num',
            'work_order_num_cardinal',
            'work_order_numbering_pattern_id',
            'work_order_old_num',
        ]);
        $clone->stage = WorkOrderStage::Estimate;
        $clone->confirmed_at = null;
        $clone->status_id = $estimateStatusId;
        $clone->source_work_order_id = $workOrder->id;
        $clone->is_estimate = true;
        $clone->is_work_order = false;
        $clone->work_order_num = null;
        $clone->work_order_num_cardinal = null;
        $clone->work_order_numbering_pattern_id = null;
        $clone->work_order_old_num = null;

        $allocation = $this->allocateNumbering($owner, WorkOrderStage::Estimate);

        if ($allocation !== null) {
            $clone->forceFill($this->numberingAttributesForStage(WorkOrderStage::Estimate, $allocation));
        } else {
            $clone->estimate_num = null;
            $clone->estimate_num_cardinal = null;
            $clone->estimate_numbering_pattern_id = null;
            $clone->estimate_old_num = null;
            $clone->code = null;
        }

        $clone->save();
        $clone->collaborators()->sync($workOrder->collaborators()->pluck('users.id')->all());

        TaskToPerform::query()
            ->where('document_id', $workOrder->id)
            ->where('document_type', TaskDocumentType::WorkOrder->value)
            ->get()
            ->each(function (TaskToPerform $task) use ($clone): void {
                $copy = $task->replicate();
                $copy->document_type = TaskDocumentType::Estimate;
                $copy->document_id = $clone->id;
                $copy->is_completed = false;
                $copy->save();
            });

        return $clone->fresh($this->defaultRelations()) ?? $clone;
    }

    /**
     * @return array<string, mixed>
     */
    public function toFormData(WorkOrder $workOrder): array
    {
        $workOrder->loadMissing([
            'status',
            'lines',
            'technicians',
            'collaborators',
            'currency:id,name,code',
            'sourceWorkOrder:id,code,subject',
        ]);

        return [
            'id' => $workOrder->id,
            'code' => $workOrder->code,
            'is_estimate' => (bool) $workOrder->is_estimate,
            'is_work_order' => (bool) $workOrder->is_work_order,
            'estimate_num' => $workOrder->estimate_num,
            'estimate_num_cardinal' => $workOrder->estimate_num_cardinal,
            'estimate_numbering_pattern_id' => $workOrder->estimate_numbering_pattern_id,
            'estimate_old_num' => $workOrder->estimate_old_num,
            'work_order_num' => $workOrder->work_order_num,
            'work_order_num_cardinal' => $workOrder->work_order_num_cardinal,
            'work_order_numbering_pattern_id' => $workOrder->work_order_numbering_pattern_id,
            'work_order_old_num' => $workOrder->work_order_old_num,
            'subject' => $workOrder->subject,
            'reference' => $workOrder->reference,
            'purchase_order' => $workOrder->purchase_order,
            'stage' => $workOrder->stage instanceof WorkOrderStage
                ? $workOrder->stage->value
                : (string) $workOrder->stage,
            'confirmed_at' => $workOrder->confirmed_at?->toIso8601String(),
            'source_work_order_id' => $workOrder->source_work_order_id,
            'source_work_order_label' => $workOrder->sourceWorkOrder?->code
                ?: $workOrder->sourceWorkOrder?->subject,
            'status_id' => $workOrder->status_id,
            'status_is_open' => (bool) ($workOrder->status?->is_open ?? true),
            'work_order_type_id' => $workOrder->work_order_type_id,
            'client_priority_id' => $workOrder->client_priority_id,
            'is_urgent' => $workOrder->is_urgent,
            'establishment_id' => $workOrder->establishment_id,
            'contract_id' => $workOrder->contract_id,
            'delegation_id' => $workOrder->delegation_id,
            'currency_id' => $workOrder->currency_id,
            'currency_label' => $workOrder->currency?->name,
            'billing_company_id' => $workOrder->billing_company_id,
            'responsible_user_id' => $workOrder->responsible_user_id,
            'requester_id' => $workOrder->requester_id,
            'notes' => $workOrder->notes,
            'internal_notes' => $workOrder->internal_notes,
            'notes_alert' => (bool) $workOrder->notes_alert,
            'internal_notes_alert' => (bool) $workOrder->internal_notes_alert,
            'received_at' => $workOrder->received_at?->format('Y-m-d\TH:i'),
            'intervention_at' => $workOrder->intervention_at?->format('Y-m-d\TH:i'),
            'due_at' => $workOrder->due_at?->format('Y-m-d\TH:i'),
            'sla_at' => $workOrder->sla_at?->format('Y-m-d\TH:i'),
            'sla_justification' => $workOrder->sla_justification,
            'sent_at' => $workOrder->sent_at?->format('Y-m-d\TH:i'),
            'closed_at' => $workOrder->closed_at?->format('Y-m-d\TH:i'),
            'created_at' => $workOrder->created_at?->format('Y-m-d\TH:i'),
            'collaborator_ids' => $workOrder->collaborators->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'lines' => $workOrder->lines->map(fn (WorkOrderLine $line): array => [
                'id' => $line->id,
                'article_id' => $line->article_id,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
            ])->values()->all(),
            'technicians' => $workOrder->technicians->map(fn (WorkOrderTechnician $technician): array => [
                'id' => $technician->id,
                'company_relationship_id' => $technician->company_relationship_id,
                'is_selected' => $technician->is_selected,
                'quote_net_amount' => $technician->quote_net_amount,
                'quoted_at' => $technician->quoted_at?->format('Y-m-d'),
                'quote_total_euros' => $technician->quote_total_euros,
                'status_id' => $technician->status_id,
                'attendance_confirmation_type_id' => $technician->attendance_confirmation_type_id,
            ])->values()->all(),
            'tasks' => $this->tasksForDocument($workOrder)->map(fn (TaskToPerform $task): array => [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'is_completed' => $task->is_completed,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toListItem(WorkOrder $workOrder): array
    {
        [$totalEuros, $costAmount] = $this->moneyFromDocument($workOrder);
        $marginPercentage = null;

        if ($totalEuros !== null && $costAmount !== null) {
            if ($totalEuros === 0.0 && $costAmount !== 0.0) {
                $marginPercentage = -100.0;
            } elseif ($totalEuros === 0.0 && $costAmount === 0.0) {
                $marginPercentage = 0.0;
            } else {
                $marginPercentage = round((1 - ($costAmount / $totalEuros)) * 100, 2);
                $marginPercentage = max(-100.0, min(100.0, $marginPercentage));
            }
        }

        $selectedTechnician = $workOrder->relationLoaded('technicians')
            ? $workOrder->technicians->firstWhere('is_selected', true) ?? $workOrder->technicians->first()
            : null;
        $technicianCompany = $selectedTechnician?->technician?->relatedCompany;

        return [
            'id' => $workOrder->id,
            'code' => $workOrder->code,
            'is_estimate' => (bool) $workOrder->is_estimate,
            'is_work_order' => (bool) $workOrder->is_work_order,
            'estimate_num' => $workOrder->estimate_num,
            'work_order_num' => $workOrder->work_order_num,
            'subject' => $workOrder->subject,
            'stage' => $workOrder->stage instanceof WorkOrderStage
                ? $workOrder->stage->value
                : (string) $workOrder->stage,
            'establishment_name' => $workOrder->establishment?->name,
            'status_name' => $workOrder->status?->name,
            'status_color' => $workOrder->status?->color,
            'priority_name' => $workOrder->priority?->name,
            'priority_color' => $workOrder->priority?->color,
            'type_name' => $workOrder->type?->name,
            'type_color' => $workOrder->type?->color,
            'status_id' => $workOrder->status_id !== null ? (int) $workOrder->status_id : null,
            'responsible_user_name' => $workOrder->responsibleUser?->name,
            'technician_name' => $technicianCompany?->tradename ?: $technicianCompany?->name,
            'is_urgent' => $workOrder->is_urgent,
            'total_euros' => $totalEuros,
            'cost_amount' => $costAmount,
            'margin_percentage' => $marginPercentage,
            'intervention_at' => $workOrder->intervention_at?->toIso8601String(),
            // Estimates use due_at as expected close (legacy fecha_cierre_esperado).
            'expected_close_at' => ($workOrder->due_at ?? $workOrder->expected_close_at)?->toIso8601String(),
            'closed_at' => $workOrder->closed_at?->toIso8601String(),
            'created_at' => $workOrder->created_at?->toIso8601String(),
        ];
    }

    /**
     * Prefer live lines/technicians (same as edit header); fall back to denormalized columns.
     *
     * @return array{0: float|null, 1: float|null}
     */
    private function moneyFromDocument(WorkOrder $workOrder): array
    {
        $totalEuros = null;
        $costAmount = null;

        if ($workOrder->relationLoaded('lines')) {
            $totalEuros = round((float) $workOrder->lines->sum(function (WorkOrderLine $line): float {
                if ($line->net_amount !== null) {
                    return (float) $line->net_amount;
                }

                return (float) $line->quantity * (float) $line->unit_price;
            }), 2);
        } elseif ($workOrder->total_euros !== null) {
            $totalEuros = (float) $workOrder->total_euros;
        } elseif ($workOrder->total_amount !== null) {
            $totalEuros = (float) $workOrder->total_amount;
        } elseif ($workOrder->net_amount !== null) {
            $totalEuros = (float) $workOrder->net_amount;
        }

        if ($workOrder->relationLoaded('technicians')) {
            $costAmount = round((float) $workOrder->technicians
                ->where('is_selected', true)
                ->sum(function (WorkOrderTechnician $technician): float {
                    if ($technician->quote_total_euros !== null) {
                        return (float) $technician->quote_total_euros;
                    }

                    return (float) ($technician->quote_net_amount ?? 0);
                }), 2);
        } elseif ($workOrder->cost_amount !== null) {
            $costAmount = (float) $workOrder->cost_amount;
        }

        return [$totalEuros, $costAmount];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, WorkOrderStage $stage): array
    {
        $statusId = isset($data['status_id']) ? (int) $data['status_id'] : null;

        if ($statusId !== null && ! (
            ($stage === WorkOrderStage::Estimate && $this->statuses->statusConfirmsEstimate($statusId))
            || ($stage === WorkOrderStage::WorkOrder && $this->statuses->statusRejectsToEstimate($statusId))
        )) {
            $this->assertStatusMatchesStage($stage, $statusId);
        }

        return [
            'code' => $data['code'] ?? null,
            'subject' => $data['subject'] ?? null,
            'reference' => $data['reference'] ?? null,
            'purchase_order' => $data['purchase_order'] ?? null,
            'stage' => $stage,
            'status_id' => $statusId,
            'work_order_type_id' => $data['work_order_type_id'] ?? null,
            'client_priority_id' => $data['client_priority_id'] ?? null,
            'is_urgent' => (bool) ($data['is_urgent'] ?? false),
            'establishment_id' => $data['establishment_id'] ?? null,
            'contract_id' => $data['contract_id'] ?? null,
            'delegation_id' => $data['delegation_id'] ?? null,
            'currency_id' => $data['currency_id'] ?? null,
            'billing_company_id' => $data['billing_company_id'] ?? null,
            'responsible_user_id' => $data['responsible_user_id'] ?? null,
            'requester_id' => $data['requester_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'internal_notes' => $data['internal_notes'] ?? null,
            'notes_alert' => (bool) ($data['notes_alert'] ?? false),
            'internal_notes_alert' => (bool) ($data['internal_notes_alert'] ?? false),
            'received_at' => $data['received_at'] ?? null,
            'intervention_at' => $data['intervention_at'] ?? null,
            'due_at' => $data['due_at'] ?? null,
            'sla_at' => $data['sla_at'] ?? null,
            'sla_justification' => $data['sla_justification'] ?? null,
        ];
    }

    private function applyStatusSideEffects(WorkOrder $workOrder, int $oldStatusId, bool $wasOpen): void
    {
        $statusId = $workOrder->status_id !== null ? (int) $workOrder->status_id : null;
        $payload = [];

        if (
            $workOrder->isEstimate()
            && $this->statuses->statusSetsSentAt($statusId)
            && $workOrder->sent_at === null
        ) {
            $payload['sent_at'] = now();
        }

        $isOpen = $this->statuses->isOpen($statusId);

        if (! $isOpen && $workOrder->closed_at === null) {
            $payload['closed_at'] = now();
        }

        if ($isOpen && ! $wasOpen) {
            $payload['closed_at'] = null;
        }

        if ($payload !== []) {
            $workOrder->forceFill($payload)->save();
        }
    }

    private function actorCanUpdateClosed(?User $actor, WorkOrder $workOrder): bool
    {
        if ($actor === null) {
            return false;
        }

        if ($workOrder->isEstimate()) {
            return app(EstimatePolicy::class)->updateClosed($actor, $workOrder);
        }

        return $actor->can('updateClosed', $workOrder);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertClosedFieldsUnchanged(WorkOrder $workOrder, array $data): void
    {
        $comparisons = [
            'subject' => $workOrder->subject,
            'notes' => $workOrder->notes,
            'internal_notes' => $workOrder->internal_notes,
            'establishment_id' => $workOrder->establishment_id !== null ? (int) $workOrder->establishment_id : null,
            'contract_id' => $workOrder->contract_id !== null ? (int) $workOrder->contract_id : null,
            'work_order_type_id' => $workOrder->work_order_type_id !== null ? (int) $workOrder->work_order_type_id : null,
            'client_priority_id' => $workOrder->client_priority_id !== null ? (int) $workOrder->client_priority_id : null,
            'responsible_user_id' => $workOrder->responsible_user_id !== null ? (int) $workOrder->responsible_user_id : null,
            'requester_id' => $workOrder->requester_id !== null ? (int) $workOrder->requester_id : null,
        ];

        $errors = [];

        foreach ($comparisons as $key => $current) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $incoming = $data[$key];
            $incoming = $incoming === '' ? null : $incoming;
            $current = $current === '' ? null : $current;

            if ($incoming != $current) {
                $errors[$key] = 'This document is closed and cannot be edited.';
            }
        }

        if (array_key_exists('is_urgent', $data) && (bool) $data['is_urgent'] !== (bool) $workOrder->is_urgent) {
            $errors['is_urgent'] = 'This document is closed and cannot be edited.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncChildren(WorkOrder $workOrder, array $data): void
    {
        $collaboratorIds = array_values(array_unique(array_filter(
            array_map('intval', (array) ($data['collaborator_ids'] ?? [])),
            fn (int $id): bool => $id > 0,
        )));
        $workOrder->collaborators()->sync($collaboratorIds);

        $this->syncLines($workOrder, (array) ($data['lines'] ?? []));
        $this->syncTechnicians($workOrder, (array) ($data['technicians'] ?? []));
        $this->syncTasks($workOrder, (array) ($data['tasks'] ?? []));
    }

    /**
     * @param  list<array<string, mixed>>  $tasks
     */
    private function syncTasks(WorkOrder $workOrder, array $tasks): void
    {
        $documentType = $this->taskDocumentType($workOrder);
        $kept = [];

        foreach (array_values($tasks) as $task) {
            if (! is_array($task)) {
                continue;
            }

            $title = trim((string) ($task['title'] ?? ''));

            if ($title === '') {
                continue;
            }

            $payload = [
                'title' => $title,
                'description' => filled($task['description'] ?? null) ? (string) $task['description'] : null,
                'is_completed' => (bool) ($task['is_completed'] ?? false),
                'document_type' => $documentType,
                'document_id' => $workOrder->id,
            ];

            $id = isset($task['id']) ? (int) $task['id'] : 0;
            $existing = $id > 0
                ? TaskToPerform::query()
                    ->whereKey($id)
                    ->where('document_id', $workOrder->id)
                    ->where('document_type', $documentType->value)
                    ->first()
                : null;

            if ($existing instanceof TaskToPerform) {
                $existing->update($payload);
                $kept[] = $existing->id;
            } else {
                $created = TaskToPerform::query()->create($payload);
                $kept[] = $created->id;
            }
        }

        TaskToPerform::query()
            ->where('document_id', $workOrder->id)
            ->where('document_type', $documentType->value)
            ->when($kept !== [], fn ($query) => $query->whereNotIn('id', $kept))
            ->get()
            ->each(function (TaskToPerform $task): void {
                $task->delete();
            });
    }

    private function taskDocumentType(WorkOrder $workOrder): TaskDocumentType
    {
        return $workOrder->isEstimate()
            ? TaskDocumentType::Estimate
            : TaskDocumentType::WorkOrder;
    }

    /**
     * @return Collection<int, TaskToPerform>
     */
    private function tasksForDocument(WorkOrder $workOrder)
    {
        return TaskToPerform::query()
            ->where('document_id', $workOrder->id)
            ->where('document_type', $this->taskDocumentType($workOrder)->value)
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function syncLines(WorkOrder $workOrder, array $lines): void
    {
        $kept = [];

        foreach (array_values($lines) as $index => $line) {
            if (! is_array($line)) {
                continue;
            }

            $quantity = (float) ($line['quantity'] ?? 1);
            $unitPrice = (float) ($line['unit_price'] ?? 0);
            $payload = [
                'article_id' => filled($line['article_id'] ?? null) ? (int) $line['article_id'] : null,
                'description' => filled($line['description'] ?? null) ? (string) $line['description'] : null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'net_amount' => round($quantity * $unitPrice, 2),
                'sort_order' => $index + 1,
            ];

            $id = isset($line['id']) ? (int) $line['id'] : 0;
            $existing = $id > 0
                ? $workOrder->lines()->whereKey($id)->first()
                : null;

            if ($existing instanceof WorkOrderLine) {
                $existing->update($payload);
                $kept[] = $existing->id;
            } else {
                $created = $workOrder->lines()->create($payload);
                $kept[] = $created->id;
            }
        }

        $workOrder->lines()->whereNotIn('id', $kept === [] ? [0] : $kept)->delete();
    }

    /**
     * Header money like optima_prod ResumenDeTrabajo:
     * - Base = sum of billing line nets (qty × unit price)
     * - Coste = sum of selected technicians' quote_total_euros
     * - Margen = base − coste
     */
    private function refreshHeaderTotals(WorkOrder $workOrder): void
    {
        $workOrder->load(['lines', 'technicians']);

        $base = round((float) $workOrder->lines->sum(function (WorkOrderLine $line): float {
            if ($line->net_amount !== null) {
                return (float) $line->net_amount;
            }

            return (float) $line->quantity * (float) $line->unit_price;
        }), 2);

        $cost = round((float) $workOrder->technicians
            ->where('is_selected', true)
            ->sum(function (WorkOrderTechnician $technician): float {
                if ($technician->quote_total_euros !== null) {
                    return (float) $technician->quote_total_euros;
                }

                return (float) ($technician->quote_net_amount ?? 0);
            }), 2);

        $margin = round($base - $cost, 2);

        $workOrder->forceFill([
            'net_amount' => $base,
            'total_amount' => $base,
            'total_euros' => $base,
            'cost_amount' => $cost,
            'margin_amount' => $margin,
        ])->save();
    }

    /**
     * @param  list<array<string, mixed>>  $technicians
     */
    private function syncTechnicians(WorkOrder $workOrder, array $technicians): void
    {
        $kept = [];
        $seenRelationships = [];

        foreach (array_values($technicians) as $row) {
            if (! is_array($row) || ! filled($row['company_relationship_id'] ?? null)) {
                continue;
            }

            $relationshipId = (int) $row['company_relationship_id'];

            // Unique (work_order_id, company_relationship_id): keep one row per technician.
            if (isset($seenRelationships[$relationshipId])) {
                continue;
            }
            $seenRelationships[$relationshipId] = true;

            $payload = [
                'company_relationship_id' => $relationshipId,
                'is_selected' => (bool) ($row['is_selected'] ?? false),
                'quote_net_amount' => filled($row['quote_net_amount'] ?? null) ? $row['quote_net_amount'] : null,
                'quoted_at' => filled($row['quoted_at'] ?? null) ? $row['quoted_at'] : null,
                'quote_total_euros' => filled($row['quote_total_euros'] ?? null) ? $row['quote_total_euros'] : null,
                'status_id' => filled($row['status_id'] ?? null) ? (int) $row['status_id'] : null,
                'attendance_confirmation_type_id' => filled($row['attendance_confirmation_type_id'] ?? null)
                    ? (int) $row['attendance_confirmation_type_id']
                    : null,
            ];

            $id = isset($row['id']) ? (int) $row['id'] : 0;
            $existing = null;

            if ($id > 0) {
                $existing = $workOrder->technicians()->withTrashed()->whereKey($id)->first();
            }

            if ($existing === null) {
                $existing = $workOrder->technicians()
                    ->withTrashed()
                    ->where('company_relationship_id', $relationshipId)
                    ->first();
            }

            if ($existing instanceof WorkOrderTechnician) {
                if ($existing->trashed()) {
                    $existing->restore();
                }

                $existing->update($payload);
                $kept[] = $existing->id;
            } else {
                $created = $workOrder->technicians()->create($payload);
                $kept[] = $created->id;
            }
        }

        $workOrder->technicians()->whereNotIn('id', $kept === [] ? [0] : $kept)->delete();
    }

    /**
     * Same source as optima_back OT/presupuesto: establecimiento → delegacion → moneda.
     *
     * @return array{delegation_id: int|null, currency_id: int|null}
     */
    private function delegationAndCurrencyFromEstablishment(int $establishmentId): array
    {
        $establishment = Establishment::query()
            ->whereKey($establishmentId)
            ->with('delegation:id,currency_id')
            ->first(['id', 'delegation_id']);

        if ($establishment === null) {
            return ['delegation_id' => null, 'currency_id' => null];
        }

        return [
            'delegation_id' => $establishment->delegation_id !== null
                ? (int) $establishment->delegation_id
                : null,
            'currency_id' => $establishment->delegation?->currency_id !== null
                ? (int) $establishment->delegation->currency_id
                : null,
        ];
    }

    private function assertStatusMatchesStage(WorkOrderStage $stage, int $statusId): void
    {
        $kind = WorkOrderStatus::query()->whereKey($statusId)->value('kind');
        $kindValue = $kind instanceof WorkOrderStage ? $kind->value : (string) $kind;

        if ($kindValue !== $stage->value) {
            throw ValidationException::withMessages([
                'status_id' => 'The selected status does not match this document stage.',
            ]);
        }
    }

    public function numberingResourceFor(WorkOrderStage $stage): string
    {
        return $stage === WorkOrderStage::Estimate
            ? NumberingResource::Estimates->value
            : NumberingResource::WorkOrders->value;
    }

    public function allocateCode(Company $owner, WorkOrderStage $stage): ?string
    {
        $allocation = $this->allocateNumbering($owner, $stage);

        return $allocation['code'] ?? null;
    }

    /**
     * @return array{code: string, cardinal: int, numbering_pattern_id: int}|null
     */
    public function allocateNumbering(Company $owner, WorkOrderStage $stage): ?array
    {
        $resource = $this->numberingResourceFor($stage);
        $existing = $this->numbering->findForResource($owner, $resource);

        // Inactive pattern → manual codes only (caller keeps request code).
        if ($existing !== null && ! $existing->is_active) {
            return null;
        }

        return $this->numbering->allocateNextDetails($owner, $resource);
    }

    /**
     * @param  array{code: string, cardinal: int, numbering_pattern_id: int}  $allocation
     * @return array<string, mixed>
     */
    private function numberingAttributesForStage(WorkOrderStage $stage, array $allocation): array
    {
        if ($stage === WorkOrderStage::Estimate) {
            return [
                'estimate_num' => $allocation['code'],
                'estimate_num_cardinal' => $allocation['cardinal'],
                'estimate_numbering_pattern_id' => $allocation['numbering_pattern_id'],
                'code' => $allocation['code'],
            ];
        }

        return [
            'work_order_num' => $allocation['code'],
            'work_order_num_cardinal' => $allocation['cardinal'],
            'work_order_numbering_pattern_id' => $allocation['numbering_pattern_id'],
            'code' => $allocation['code'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function manualNumberingAttributesForStage(WorkOrderStage $stage, string $code): array
    {
        $trimmed = trim($code);

        if ($stage === WorkOrderStage::Estimate) {
            return [
                'estimate_num' => $trimmed !== '' ? $trimmed : null,
                'estimate_num_cardinal' => null,
                'estimate_numbering_pattern_id' => null,
                'code' => $trimmed !== '' ? $trimmed : null,
            ];
        }

        return [
            'work_order_num' => $trimmed !== '' ? $trimmed : null,
            'work_order_num_cardinal' => null,
            'work_order_numbering_pattern_id' => null,
            'code' => $trimmed !== '' ? $trimmed : null,
        ];
    }

    /**
     * Keep stage-specific numbering columns aligned when the display code changes.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function syncNumberingFromCodeAttributes(WorkOrder $workOrder, array $data): array
    {
        if (! array_key_exists('code', $data)) {
            return [];
        }

        $stage = $workOrder->stage instanceof WorkOrderStage
            ? $workOrder->stage
            : WorkOrderStage::from((string) $workOrder->stage);

        $requested = trim((string) ($data['code'] ?? ''));
        $current = $stage === WorkOrderStage::Estimate
            ? trim((string) ($workOrder->estimate_num ?? ''))
            : trim((string) ($workOrder->work_order_num ?? ''));

        if ($requested === '' || $requested === $current) {
            return [];
        }

        $attributes = $this->manualNumberingAttributesForStage($stage, $requested);

        if ($stage === WorkOrderStage::Estimate && $current !== '') {
            $attributes['estimate_old_num'] = $current;
        }

        if ($stage === WorkOrderStage::WorkOrder && $current !== '') {
            $attributes['work_order_old_num'] = $current;
        }

        return $attributes;
    }

    /**
     * @return list<string>
     */
    private function defaultRelations(): array
    {
        return ['establishment', 'status', 'responsibleUser', 'type', 'lines', 'technicians', 'collaborators'];
    }

    /**
     * @return list<array{id: int, label: string, color: string|null}>
     */
    public function technicianStatusOptions(): array
    {
        return WorkOrderTechnicianStatus::query()
            ->orderBy('id')
            ->get(['id', 'name', 'color'])
            ->map(fn (WorkOrderTechnicianStatus $status): array => [
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
    public function attendanceTypeOptions(): array
    {
        return TechnicianAttendanceConfirmationType::query()
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn (TechnicianAttendanceConfirmationType $type): array => [
                'id' => $type->id,
                'label' => $type->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, completed: bool}>
     */
    public function checklistItemsForWorkOrder(WorkOrder $workOrder): array
    {
        $statusId = $workOrder->status_id !== null ? (int) $workOrder->status_id : null;

        if ($statusId === null) {
            return [];
        }

        $completedIds = WorkOrderChecklistCompletion::query()
            ->where('work_order_id', $workOrder->id)
            ->pluck('checklist_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return Checklist::query()
            ->where('document_type', ChecklistDocumentType::WorkOrder->value)
            ->where('work_order_status_id', $statusId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'label'])
            ->map(fn (Checklist $checklist): array => [
                'id' => $checklist->id,
                'name' => $checklist->label,
                'completed' => in_array($checklist->id, $completedIds, true),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncChecklistCompletions(WorkOrder $workOrder, array $data, ?User $actor): void
    {
        if (! array_key_exists('checklist_completions', $data)) {
            return;
        }

        $ids = array_values(array_unique(array_filter(
            array_map('intval', (array) $data['checklist_completions']),
            fn (int $id): bool => $id > 0,
        )));

        WorkOrderChecklistCompletion::query()
            ->where('work_order_id', $workOrder->id)
            ->when($ids !== [], fn ($query) => $query->whereNotIn('checklist_id', $ids))
            ->when($ids === [], fn ($query) => $query)
            ->delete();

        foreach ($ids as $checklistId) {
            WorkOrderChecklistCompletion::query()->updateOrCreate(
                [
                    'work_order_id' => $workOrder->id,
                    'checklist_id' => $checklistId,
                ],
                [
                    'user_id' => $actor?->id,
                    'is_validated' => true,
                ],
            );
        }
    }

    /**
     * Prod UpdateOtUseCase rules that map cleanly onto v2 statuses.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertWorkOrderBusinessRules(
        WorkOrder $workOrder,
        array $data,
        int $oldStatusId,
        int $newStatusId,
        ?User $actor,
    ): void {
        $technicians = array_values(array_filter(
            (array) ($data['technicians'] ?? []),
            static fn (mixed $row): bool => is_array($row) && filled($row['company_relationship_id'] ?? null),
        ));

        $interventionAt = filled($data['intervention_at'] ?? null) ? (string) $data['intervention_at'] : null;
        $oldIntervention = $workOrder->intervention_at?->format('Y-m-d H:i');
        $newIntervention = $interventionAt !== null
            ? Carbon::parse($interventionAt)->format('Y-m-d H:i')
            : null;

        // En Progreso (18) / En Espera de Material (16): cannot change intervention while staying in status.
        if (
            in_array($oldStatusId, [16, 18], true)
            && $newStatusId === $oldStatusId
            && $oldIntervention !== null
            && $newIntervention !== null
            && $oldIntervention !== $newIntervention
        ) {
            throw ValidationException::withMessages([
                'intervention_at' => 'Intervention date cannot be changed in this status.',
            ]);
        }

        // En Espera del Cliente (17) requires intervention date.
        if ($newStatusId === 17 && $interventionAt === null) {
            throw ValidationException::withMessages([
                'intervention_at' => 'Intervention date is required for this status.',
            ]);
        }

        $targetStatus = WorkOrderStatus::query()->find($newStatusId);
        $isClosedNonReject = $targetStatus !== null
            && ! $targetStatus->is_open
            && ! (bool) $targetStatus->rejects_to_estimate
            && ! in_array($newStatusId, [11, 12], true);

        // Technicians required when entering En Progreso or a closed (non-reject) status.
        $enteringTechnicianRequiredStatus = $newStatusId !== $oldStatusId
            && ($newStatusId === 18 || $isClosedNonReject);

        if ($enteringTechnicianRequiredStatus && $technicians === []) {
            throw ValidationException::withMessages([
                'technicians' => 'At least one technician is required for this status.',
            ]);
        }

        // Near En Progreso: cannot change tasks within 1 hour of intervention.
        if (
            $oldStatusId === 18
            && $newStatusId !== 18
            && $workOrder->intervention_at !== null
            && $workOrder->intervention_at->lte(now()->addHour())
        ) {
            $incomingTasks = collect((array) ($data['tasks'] ?? []))
                ->map(fn (mixed $task): array => is_array($task) ? [
                    'title' => trim((string) ($task['title'] ?? '')),
                    'description' => trim((string) ($task['description'] ?? '')),
                    'is_completed' => (bool) ($task['is_completed'] ?? false),
                ] : [])
                ->values()
                ->all();
            $currentTasks = $this->tasksForDocument($workOrder)
                ->map(fn (TaskToPerform $task): array => [
                    'title' => trim((string) $task->title),
                    'description' => trim((string) ($task->description ?? '')),
                    'is_completed' => (bool) $task->is_completed,
                ])
                ->values()
                ->all();

            if ($incomingTasks !== $currentTasks) {
                throw ValidationException::withMessages([
                    'tasks' => 'Tasks cannot be modified while the work order is in progress near the intervention time.',
                ]);
            }
        }

        // Establishment change only while open.
        $newEstablishmentId = filled($data['establishment_id'] ?? null) ? (int) $data['establishment_id'] : null;
        if (
            $newEstablishmentId !== null
            && $workOrder->establishment_id !== null
            && $newEstablishmentId !== (int) $workOrder->establishment_id
            && ! (bool) ($workOrder->status?->is_open ?? true)
        ) {
            throw ValidationException::withMessages([
                'establishment_id' => 'Establishment cannot be changed on a closed work order.',
            ]);
        }

        // Preventivo priority: block certain status transitions without permission.
        $priorityId = filled($data['client_priority_id'] ?? null)
            ? (int) $data['client_priority_id']
            : ($workOrder->client_priority_id !== null ? (int) $workOrder->client_priority_id : null);
        $preventive = ClientPriority::query()->whereKey($priorityId)->where('code', 'PREVENTIVO')->exists()
            || ClientPriority::query()->whereKey($priorityId)->where('name', 'like', '%Preventiv%')->exists();

        if ($preventive && in_array($newStatusId, [12, 13, 16, 17], true)) {
            $canClose = $actor?->can('work_orders.update_closed') || $actor?->hasRole('Admin');

            if (! $canClose && in_array($newStatusId, [11, 12], true)) {
                throw ValidationException::withMessages([
                    'status_id' => 'You do not have permission to close a preventive work order.',
                ]);
            }

            if (in_array($newStatusId, [13, 16, 17], true)) {
                throw ValidationException::withMessages([
                    'status_id' => 'A preventive work order cannot be changed to this status.',
                ]);
            }
        }
    }
}
