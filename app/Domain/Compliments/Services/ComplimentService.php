<?php

declare(strict_types=1);

namespace App\Domain\Compliments\Services;

use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\Companies\Support\CompanyMemberUsers;
use App\Domain\Compliments\Enums\ComplimentSubjectType;
use App\Domain\QualityScores\Services\QualityScoreProcessor;
use App\Models\Brand;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\Compliment;
use App\Models\ComplimentType;
use App\Models\Establishment;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as LengthAwarePaginatorConcrete;
use Illuminate\Support\Facades\DB;

final class ComplimentService
{
    public function __construct(
        private readonly QualityScoreProcessor $qualityScores,
    ) {}

    /**
     * @return list<int>
     */
    public function accessibleCustomerRelationshipIds(Company $owner): array
    {
        return $owner->ownedRelationships()
            ->where('kind', CompanyRelationshipKind::Customer->value)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
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
     * Brand IDs used by accessible customers (relationship or party company).
     *
     * @return list<int>
     */
    public function accessibleBrandIds(Company $owner): array
    {
        $relationshipBrandIds = $owner->ownedRelationships()
            ->where('kind', CompanyRelationshipKind::Customer->value)
            ->whereNotNull('brand_id')
            ->pluck('brand_id');

        $companyIds = $this->accessibleCompanyIds($owner);
        $companyBrandIds = $companyIds === []
            ? collect()
            : Company::query()->whereIn('id', $companyIds)->whereNotNull('brand_id')->pluck('brand_id');

        return $relationshipBrandIds
            ->merge($companyBrandIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    public function accessibleEstablishmentIds(Company $owner): array
    {
        $companyIds = $this->accessibleCompanyIds($owner);

        if ($companyIds === []) {
            return [];
        }

        return Establishment::query()
            ->whereIn('company_id', $companyIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null, subject_type?: string|null, compliment_type_id?: string|null, created_from?: string|null, created_to?: string|null}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateForWeb(Company $owner, array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $subjectType = trim((string) ($filters['subject_type'] ?? ''));
        $complimentTypeId = trim((string) ($filters['compliment_type_id'] ?? ''));
        $createdFrom = trim((string) ($filters['created_from'] ?? ''));
        $createdTo = trim((string) ($filters['created_to'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort(
            $filters,
            ['id', 'created_at', 'comment'],
            'id',
            'desc',
        );

        $paginatorQuery = $this->scopedQuery($owner)
            ->with([
                'type:id,name',
                'brand:id,name',
                'companyRelationship.relatedCompany:id,name,tradename,logo',
                'establishment:id,name,code',
                'users:id,name',
            ])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner
                        ->where('compliments.id', 'like', "%{$search}%")
                        ->orWhere('compliments.comment', 'like', "%{$search}%")
                        ->orWhereHas('type', fn (Builder $q) => $q->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('brand', fn (Builder $q) => $q->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('establishment', fn (Builder $q) => $q->where('name', 'like', "%{$search}%"))
                        ->orWhereHas(
                            'companyRelationship.relatedCompany',
                            fn (Builder $q) => $q->where('name', 'like', "%{$search}%")
                                ->orWhere('tradename', 'like', "%{$search}%"),
                        )
                        ->orWhereHas('users', fn (Builder $q) => $q->where('name', 'like', "%{$search}%"));
                });
            })
            ->when(
                $subjectType !== '' && in_array($subjectType, ComplimentSubjectType::values(), true),
                fn (Builder $query) => $query->where('compliments.subject_type', $subjectType),
            )
            ->when($complimentTypeId !== '', fn (Builder $query) => $query->where('compliments.compliment_type_id', (int) $complimentTypeId))
            ->when($createdFrom !== '', fn (Builder $query) => $query->whereDate('compliments.created_at', '>=', $createdFrom))
            ->when($createdTo !== '', fn (Builder $query) => $query->whereDate('compliments.created_at', '<=', $createdTo))
            ->orderBy("compliments.{$sort}", $direction);

        // Scoped OR + subqueries make COUNT(*) expensive; page fetch is cheap. Detect has-more only.
        $page = max(1, (int) ($filters['page'] ?? LengthAwarePaginatorConcrete::resolveCurrentPage()));
        $rows = (clone $paginatorQuery)
            ->forPage($page, $perPage + 1)
            ->get();
        $hasMore = $rows->count() > $perPage;
        $items = $rows->take($perPage)->values();
        $total = (($page - 1) * $perPage) + $items->count() + ($hasMore ? 1 : 0);

        $paginator = new LengthAwarePaginatorConcrete(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginatorConcrete::resolveCurrentPath(),
                'pageName' => 'page',
            ],
        );
        $paginator->withQueryString();

        return $paginator->through(fn (Compliment $compliment): array => $this->toListItem($compliment));
    }

    /**
     * @param  array{
     *     subject_type: string,
     *     brand_id?: int|null,
     *     company_relationship_id?: int|null,
     *     establishment_id?: int|null,
     *     compliment_type_id: int,
     *     comment?: string|null,
     *     user_ids: list<int>,
     *     score: int
     * }  $data
     */
    public function create(array $data): Compliment
    {
        return DB::transaction(function () use ($data): Compliment {
            $compliment = Compliment::query()->create($this->subjectAttributes($data) + [
                'compliment_type_id' => $data['compliment_type_id'],
                'comment' => $data['comment'] ?? null,
            ]);

            $this->syncUsers($compliment, $data['user_ids'], (int) $data['score']);
            $compliment->load('users');
            $this->qualityScores->processCompliment($compliment);

            return $compliment;
        });
    }

    /**
     * @param  array{
     *     subject_type: string,
     *     brand_id?: int|null,
     *     company_relationship_id?: int|null,
     *     establishment_id?: int|null,
     *     compliment_type_id: int,
     *     comment?: string|null,
     *     user_ids: list<int>,
     *     score: int
     * }  $data
     */
    public function update(Compliment $compliment, array $data): Compliment
    {
        return DB::transaction(function () use ($compliment, $data): Compliment {
            $compliment->update($this->subjectAttributes($data) + [
                'compliment_type_id' => $data['compliment_type_id'],
                'comment' => $data['comment'] ?? null,
            ]);

            $this->syncUsers($compliment, $data['user_ids'], (int) $data['score']);
            $compliment->load('users');
            $this->qualityScores->processCompliment($compliment);

            return $compliment->fresh(['type', 'brand', 'companyRelationship.relatedCompany', 'establishment', 'users'])
                ?? $compliment;
        });
    }

    public function delete(Compliment $compliment): void
    {
        if ($compliment->trashed()) {
            return;
        }

        DB::transaction(function () use ($compliment): void {
            $compliment->delete();
        });
    }

    public function canAccess(Company $owner, Compliment $compliment): bool
    {
        $ownerId = (int) $owner->id;
        $customerKind = CompanyRelationshipKind::Customer->value;

        return match ($compliment->subject_type) {
            ComplimentSubjectType::Brand => $compliment->brand_id !== null && (
                CompanyRelationship::query()
                    ->where('owner_company_id', $ownerId)
                    ->where('kind', $customerKind)
                    ->where('brand_id', (int) $compliment->brand_id)
                    ->exists()
                || Company::query()
                    ->where('brand_id', (int) $compliment->brand_id)
                    ->whereIn('id', function ($sub) use ($ownerId, $customerKind): void {
                        $sub->select('related_company_id')
                            ->from('company_relationships')
                            ->where('owner_company_id', $ownerId)
                            ->where('kind', $customerKind)
                            ->whereNull('deleted_at');
                    })
                    ->exists()
            ),
            ComplimentSubjectType::Customer => $compliment->company_relationship_id !== null
                && CompanyRelationship::query()
                    ->whereKey((int) $compliment->company_relationship_id)
                    ->where('owner_company_id', $ownerId)
                    ->where('kind', $customerKind)
                    ->exists(),
            ComplimentSubjectType::Establishment => $compliment->establishment_id !== null
                && Establishment::query()
                    ->whereKey((int) $compliment->establishment_id)
                    ->whereIn('company_id', function ($sub) use ($ownerId, $customerKind): void {
                        $sub->select('related_company_id')
                            ->from('company_relationships')
                            ->where('owner_company_id', $ownerId)
                            ->where('kind', $customerKind)
                            ->whereNull('deleted_at');
                    })
                    ->exists(),
            default => false,
        };
    }

    /**
     * Seed options for Inertia pages. Full brand lists load via /select-options/brands.
     *
     * @return list<array{id: int, label: string}>
     */
    public function brandOptions(Company $owner, ?int $includeId = null): array
    {
        unset($owner);

        return Brand::query()
            ->when(
                $includeId !== null && $includeId > 0,
                fn ($query) => $query->whereKey($includeId),
                fn ($query) => $query->whereRaw('0 = 1'),
            )
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Brand $brand): array => [
                'id' => $brand->id,
                'label' => $brand->name,
            ])
            ->values()
            ->all();
    }

    /**
     * Seed customer relationship options. Full lists load via /select-options/customers.
     *
     * @return list<array{id: int, label: string, logo_url: string|null}>
     */
    public function customerOptions(Company $owner, ?int $includeId = null): array
    {
        return $this->searchCustomerOptions(
            $owner,
            includeIds: $includeId !== null && $includeId > 0 ? [$includeId] : [],
            onlyIncludeIds: true,
        );
    }

    /**
     * Lightweight options for async customer (company relationship) pickers.
     *
     * @param  list<int>  $includeIds
     * @return list<array{id: int, label: string, logo_url: string|null}>
     */
    public function searchCustomerOptions(
        Company $owner,
        ?string $search = null,
        array $includeIds = [],
        ?int $limit = 50,
        bool $onlyIncludeIds = false,
    ): array {
        $includeIds = array_values(array_unique(array_filter(
            array_map('intval', $includeIds),
            fn (int $id): bool => $id > 0,
        )));

        if ($onlyIncludeIds) {
            if ($includeIds === []) {
                return [];
            }

            return CompanyRelationship::query()
                ->with('relatedCompany:id,name,tradename,logo,is_active')
                ->where('owner_company_id', $owner->id)
                ->where('kind', CompanyRelationshipKind::Customer->value)
                ->whereIn('id', $includeIds)
                ->orderBy('id')
                ->get()
                ->map(fn (CompanyRelationship $relationship): array => $this->customerSelectOption($relationship))
                ->values()
                ->all();
        }

        $needle = trim((string) $search);

        $rows = CompanyRelationship::query()
            ->with('relatedCompany:id,name,tradename,logo,is_active')
            ->where('owner_company_id', $owner->id)
            ->where('kind', CompanyRelationshipKind::Customer->value)
            ->where(function ($query) use ($includeIds): void {
                $query->whereHas('relatedCompany', fn ($company) => $company->where('is_active', true));

                if ($includeIds !== []) {
                    $query->orWhereIn('id', $includeIds);
                }
            })
            ->when($needle !== '', function ($query) use ($needle): void {
                $query->whereHas('relatedCompany', function ($company) use ($needle): void {
                    $company->where(function ($inner) use ($needle): void {
                        $inner->where('name', 'like', "%{$needle}%")
                            ->orWhere('tradename', 'like', "%{$needle}%")
                            ->orWhere('tax_id', 'like', "%{$needle}%");
                    });
                });
            })
            ->orderBy('id')
            ->limit(max(1, min($limit ?? 50, 100)))
            ->get()
            ->map(fn (CompanyRelationship $relationship): array => $this->customerSelectOption($relationship))
            ->values()
            ->all();

        if ($includeIds !== []) {
            $present = array_map(fn (array $row): int => (int) $row['id'], $rows);
            $missing = array_values(array_diff($includeIds, $present));

            if ($missing !== []) {
                $extra = CompanyRelationship::query()
                    ->with('relatedCompany:id,name,tradename,logo,is_active')
                    ->where('owner_company_id', $owner->id)
                    ->where('kind', CompanyRelationshipKind::Customer->value)
                    ->whereIn('id', $missing)
                    ->get()
                    ->map(fn (CompanyRelationship $relationship): array => $this->customerSelectOption($relationship))
                    ->all();

                $rows = array_values(array_merge($extra, $rows));
            }
        }

        return $rows;
    }

    /**
     * Seed establishment options. Full lists load via /select-options/establishments.
     *
     * @param  list<int>  $includeIds
     * @return list<array{id: int, label: string}>
     */
    public function establishmentOptions(Company $owner, array $includeIds = []): array
    {
        unset($owner);

        $includeIds = array_values(array_unique(array_filter(
            array_map('intval', $includeIds),
            fn (int $id): bool => $id > 0,
        )));

        if ($includeIds === []) {
            return [];
        }

        return Establishment::query()
            ->whereIn('id', $includeIds)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (Establishment $establishment): array => [
                'id' => $establishment->id,
                'label' => $establishment->code
                    ? "{$establishment->name} ({$establishment->code})"
                    : $establishment->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function typeOptions(): array
    {
        return ComplimentType::query()
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn (ComplimentType $type): array => [
                'id' => $type->id,
                'label' => $type->name,
            ])
            ->values()
            ->all();
    }

    /**
     * Seed user options. Full lists load via /select-options/users.
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
     * @return array{id: int, label: string, logo_url: string|null}
     */
    private function customerSelectOption(CompanyRelationship $relationship): array
    {
        $company = $relationship->relatedCompany;
        $label = $company?->name
            ?: $company?->tradename
            ?: "#{$relationship->id}";

        return $relationship->toSelectOption($label);
    }

    /**
     * @return array<string, mixed>
     */
    public function toFormData(Compliment $compliment): array
    {
        $compliment->loadMissing([
            'users',
            'type',
            'brand',
            'companyRelationship.relatedCompany',
            'establishment',
        ]);

        return [
            'id' => $compliment->id,
            'subject_type' => $compliment->subject_type->value,
            'brand_id' => $compliment->brand_id,
            'company_relationship_id' => $compliment->company_relationship_id,
            'establishment_id' => $compliment->establishment_id,
            'compliment_type_id' => $compliment->compliment_type_id,
            'comment' => $compliment->comment,
            'user_ids' => $compliment->users->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'score' => (int) ($compliment->users->first()?->pivot?->score ?? 1),
            'subject_label' => $compliment->subjectLabel(),
            'type_name' => $compliment->type?->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toListItem(Compliment $compliment): array
    {
        return [
            'id' => $compliment->id,
            'subject_type' => $compliment->subject_type->value,
            'subject_label' => $compliment->subjectLabel(),
            'type_name' => $compliment->type?->name,
            'comment' => $compliment->comment,
            'score' => $compliment->users->first()?->pivot?->score,
            'users' => $compliment->users->pluck('name')->implode(', '),
            'created_at' => $compliment->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
        ];
    }

    /**
     * Scope compliments to the owner's customers via subqueries (avoids huge whereIn ID lists in PHP).
     *
     * @return Builder<Compliment>
     */
    private function scopedQuery(Company $owner): Builder
    {
        $ownerId = (int) $owner->id;
        $customerKind = CompanyRelationshipKind::Customer->value;

        return Compliment::query()->where(function (Builder $query) use ($ownerId, $customerKind): void {
            $query->orWhere(function (Builder $inner) use ($ownerId, $customerKind): void {
                $inner->where('subject_type', ComplimentSubjectType::Brand->value)
                    ->where(function (Builder $brandScope) use ($ownerId, $customerKind): void {
                        $brandScope
                            ->whereIn('brand_id', function ($sub) use ($ownerId, $customerKind): void {
                                $sub->select('brand_id')
                                    ->from('company_relationships')
                                    ->where('owner_company_id', $ownerId)
                                    ->where('kind', $customerKind)
                                    ->whereNotNull('brand_id')
                                    ->whereNull('deleted_at');
                            })
                            ->orWhereIn('brand_id', function ($sub) use ($ownerId, $customerKind): void {
                                $sub->select('companies.brand_id')
                                    ->from('companies')
                                    ->join(
                                        'company_relationships',
                                        'company_relationships.related_company_id',
                                        '=',
                                        'companies.id',
                                    )
                                    ->where('company_relationships.owner_company_id', $ownerId)
                                    ->where('company_relationships.kind', $customerKind)
                                    ->whereNotNull('companies.brand_id')
                                    ->whereNull('company_relationships.deleted_at')
                                    ->whereNull('companies.deleted_at');
                            });
                    });
            });

            $query->orWhere(function (Builder $inner) use ($ownerId, $customerKind): void {
                $inner->where('subject_type', ComplimentSubjectType::Customer->value)
                    ->whereIn('company_relationship_id', function ($sub) use ($ownerId, $customerKind): void {
                        $sub->select('id')
                            ->from('company_relationships')
                            ->where('owner_company_id', $ownerId)
                            ->where('kind', $customerKind)
                            ->whereNull('deleted_at');
                    });
            });

            $query->orWhere(function (Builder $inner) use ($ownerId, $customerKind): void {
                $inner->where('subject_type', ComplimentSubjectType::Establishment->value)
                    ->whereIn('establishment_id', function ($sub) use ($ownerId, $customerKind): void {
                        $sub->select('establishments.id')
                            ->from('establishments')
                            ->join(
                                'company_relationships',
                                'company_relationships.related_company_id',
                                '=',
                                'establishments.company_id',
                            )
                            ->where('company_relationships.owner_company_id', $ownerId)
                            ->where('company_relationships.kind', $customerKind)
                            ->whereNull('company_relationships.deleted_at')
                            ->whereNull('establishments.deleted_at');
                    });
            });
        });
    }

    /**
     * @param  array{
     *     subject_type: string,
     *     brand_id?: int|null,
     *     company_relationship_id?: int|null,
     *     establishment_id?: int|null
     * }  $data
     * @return array{
     *     subject_type: string,
     *     brand_id: int|null,
     *     company_relationship_id: int|null,
     *     establishment_id: int|null
     * }
     */
    private function subjectAttributes(array $data): array
    {
        $type = ComplimentSubjectType::from($data['subject_type']);

        return [
            'subject_type' => $type->value,
            'brand_id' => $type === ComplimentSubjectType::Brand ? ($data['brand_id'] ?? null) : null,
            'company_relationship_id' => $type === ComplimentSubjectType::Customer
                ? ($data['company_relationship_id'] ?? null)
                : null,
            'establishment_id' => $type === ComplimentSubjectType::Establishment
                ? ($data['establishment_id'] ?? null)
                : null,
        ];
    }

    /**
     * @param  list<int>  $userIds
     */
    private function syncUsers(Compliment $compliment, array $userIds, int $score): void
    {
        $payload = [];

        foreach ($userIds as $userId) {
            $payload[(int) $userId] = ['score' => $score];
        }

        $compliment->users()->sync($payload);
    }
}
