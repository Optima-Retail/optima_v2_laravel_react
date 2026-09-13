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

        $paginator = $this->scopedQuery($owner)
            ->with([
                'type:id,name',
                'brand:id,name',
                'companyRelationship.relatedCompany:id,name,tradename',
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
            ->orderBy("compliments.{$sort}", $direction)
            ->paginate($perPage)
            ->withQueryString();

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
        return match ($compliment->subject_type) {
            ComplimentSubjectType::Brand => in_array(
                (int) $compliment->brand_id,
                $this->accessibleBrandIds($owner),
                true,
            ),
            ComplimentSubjectType::Customer => in_array(
                (int) $compliment->company_relationship_id,
                $this->accessibleCustomerRelationshipIds($owner),
                true,
            ),
            ComplimentSubjectType::Establishment => in_array(
                (int) $compliment->establishment_id,
                $this->accessibleEstablishmentIds($owner),
                true,
            ),
            default => false,
        };
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function brandOptions(Company $owner): array
    {
        $ids = $this->accessibleBrandIds($owner);

        if ($ids === []) {
            return Brand::query()
                ->orderBy('name')
                ->limit(200)
                ->get(['id', 'name'])
                ->map(fn (Brand $brand): array => [
                    'id' => $brand->id,
                    'label' => $brand->name,
                ])
                ->values()
                ->all();
        }

        return Brand::query()
            ->whereIn('id', $ids)
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
     * @return list<array{id: int, label: string}>
     */
    public function customerOptions(Company $owner): array
    {
        return CompanyRelationship::query()
            ->with('relatedCompany:id,name,tradename')
            ->where('owner_company_id', $owner->id)
            ->where('kind', CompanyRelationshipKind::Customer->value)
            ->orderBy('id')
            ->get()
            ->map(function (CompanyRelationship $relationship): array {
                $company = $relationship->relatedCompany;
                $label = $company?->name
                    ?: $company?->tradename
                    ?: "#{$relationship->id}";

                return [
                    'id' => $relationship->id,
                    'label' => $label,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function establishmentOptions(Company $owner): array
    {
        $companyIds = $this->accessibleCompanyIds($owner);

        if ($companyIds === []) {
            return [];
        }

        return Establishment::query()
            ->whereIn('company_id', $companyIds)
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
     * @param  list<int>  $includeUserIds
     * @return list<array{id: int, label: string}>
     */
    public function userOptions(Company $owner, array $includeUserIds = []): array
    {
        return CompanyMemberUsers::options($owner, $includeUserIds);
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
     * @return Builder<Compliment>
     */
    private function scopedQuery(Company $owner): Builder
    {
        $brandIds = $this->accessibleBrandIds($owner);
        $relationshipIds = $this->accessibleCustomerRelationshipIds($owner);
        $establishmentIds = $this->accessibleEstablishmentIds($owner);

        return Compliment::query()->where(function (Builder $query) use ($brandIds, $relationshipIds, $establishmentIds): void {
            if ($brandIds !== []) {
                $query->orWhere(function (Builder $inner) use ($brandIds): void {
                    $inner->where('subject_type', ComplimentSubjectType::Brand->value)
                        ->whereIn('brand_id', $brandIds);
                });
            }

            if ($relationshipIds !== []) {
                $query->orWhere(function (Builder $inner) use ($relationshipIds): void {
                    $inner->where('subject_type', ComplimentSubjectType::Customer->value)
                        ->whereIn('company_relationship_id', $relationshipIds);
                });
            }

            if ($establishmentIds !== []) {
                $query->orWhere(function (Builder $inner) use ($establishmentIds): void {
                    $inner->where('subject_type', ComplimentSubjectType::Establishment->value)
                        ->whereIn('establishment_id', $establishmentIds);
                });
            }

            // Avoid empty OR (matches everything) when owner has no clients yet.
            if ($brandIds === [] && $relationshipIds === [] && $establishmentIds === []) {
                $query->whereRaw('0 = 1');
            }
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
