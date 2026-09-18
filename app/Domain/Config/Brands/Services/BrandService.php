<?php

declare(strict_types=1);

namespace App\Domain\Config\Brands\Services;

use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\Companies\Support\CompanyMemberUsers;
use App\Models\Brand;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\Compliment;
use App\Models\FormTemplate;
use App\Models\User;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class BrandService
{
    /**
     * @param  array{
     *     search?: string|null,
     *     sort?: string|null,
     *     direction?: string|null,
     *     per_page?: int|string|null,
     *     created_at?: string|null,
     *     account_manager_ids?: string|list<int|string>|null,
     *     commercial_manager_ids?: string|list<int|string>|null,
     *     collaborator_ids?: string|list<int|string>|null,
     * }  $filters
     * @return LengthAwarePaginator<int, Brand>
     */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $createdAt = trim((string) ($filters['created_at'] ?? ''));
        $accountManagerIds = $this->intList($filters['account_manager_ids'] ?? null);
        $commercialManagerIds = $this->intList($filters['commercial_manager_ids'] ?? null);
        $collaboratorIds = $this->intList($filters['collaborator_ids'] ?? null);
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort(
            $filters,
            ['id', 'name', 'account_manager_id', 'loyalty_meeting_frequency', 'clients_count', 'created_at'],
            'name',
        );

        return Brand::query()
            ->with(['accountManager'])
            ->withCount('customers as clients_count')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%");

                    if (ctype_digit($search)) {
                        $inner->orWhere('id', (int) $search);
                    }
                });
            })
            ->when($createdAt !== '', fn ($query) => $query->whereDate('created_at', $createdAt))
            ->when($accountManagerIds !== [], fn ($query) => $query->whereIn('account_manager_id', $accountManagerIds))
            ->when($commercialManagerIds !== [], fn ($query) => $query->whereIn('commercial_manager_id', $commercialManagerIds))
            ->when($collaboratorIds !== [], function ($query) use ($collaboratorIds): void {
                $query->whereHas(
                    'collaborators',
                    fn ($users) => $users->whereIn('users.id', $collaboratorIds),
                );
            })
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array{
     *     search?: string|null,
     *     sort?: string|null,
     *     direction?: string|null,
     *     per_page?: int|string|null,
     *     created_at?: string|null,
     *     account_manager_ids?: string|list<int|string>|null,
     *     commercial_manager_ids?: string|list<int|string>|null,
     *     collaborator_ids?: string|list<int|string>|null,
     * }  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateForWeb(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        return $this->paginate($filters, $perPage)
            ->through(fn (Brand $brand): array => $this->toListItem($brand));
    }

    /**
     * @param  array{
     *     name: string,
     *     account_manager_id?: int|null,
     *     commercial_manager_id?: int|null,
     *     collaborator_ids?: list<int>,
     *     loyalty_meeting_frequency?: string|null,
     *     is_quality_control_contactable: bool,
     *     send_debt_reminders: bool
     * }  $data
     */
    public function create(array $data): Brand
    {
        return DB::transaction(function () use ($data): Brand {
            $brand = Brand::query()->create(Arr::except($data, ['collaborator_ids']));
            $brand->collaborators()->sync($data['collaborator_ids'] ?? []);

            return $brand->load(['accountManager', 'commercialManager', 'collaborators']);
        });
    }

    /**
     * @param  array{
     *     name: string,
     *     account_manager_id?: int|null,
     *     commercial_manager_id?: int|null,
     *     collaborator_ids?: list<int>,
     *     loyalty_meeting_frequency?: string|null,
     *     is_quality_control_contactable: bool,
     *     send_debt_reminders: bool
     * }  $data
     */
    public function update(Brand $brand, array $data): Brand
    {
        return DB::transaction(function () use ($brand, $data): Brand {
            $brand->update(Arr::except($data, ['collaborator_ids']));
            $brand->collaborators()->sync($data['collaborator_ids'] ?? []);

            return $brand->fresh(['accountManager', 'commercialManager', 'collaborators']);
        });
    }

    public function delete(Brand $brand): void
    {
        if ($brand->trashed()) {
            return;
        }

        if (! $this->canBeDeleted($brand)) {
            throw new \InvalidArgumentException('brand_cannot_be_deleted');
        }

        DB::transaction(function () use ($brand): void {
            User::query()->where('brand_id', $brand->id)->update(['brand_id' => null]);
            $brand->collaborators()->detach();
            $brand->messages()->delete();
            $brand->softDeleteSafely();
        });
    }

    /**
     * Operational usages that still reference this brand and block deletion.
     *
     * Owned configuration (collaborators, messages) and nullable user.brand_id do not block.
     *
     * @return list<string>
     */
    public function deletionBlockers(Brand $brand): array
    {
        $id = $brand->id;
        $blockers = [];

        if (CompanyRelationship::query()->where('brand_id', $id)->exists()) {
            $blockers[] = 'company_relationships';
        }

        if (Company::query()->where('brand_id', $id)->exists()) {
            $blockers[] = 'companies';
        }

        if (Compliment::query()->where('brand_id', $id)->exists()) {
            $blockers[] = 'compliments';
        }

        if (FormTemplate::query()->where('brand_id', $id)->exists()) {
            $blockers[] = 'form_templates';
        }

        if (DB::table('work_order_type_form_templates')->where('brand_id', $id)->exists()) {
            $blockers[] = 'work_order_type_form_templates';
        }

        return $blockers;
    }

    public function canBeDeleted(Brand $brand): bool
    {
        return $this->deletionBlockers($brand) === [];
    }

    /**
     * @param  list<int>  $includeUserIds
     * @return list<array{id: int, label: string}>
     */
    public function userOptions(?Company $owner = null, array $includeUserIds = []): array
    {
        return CompanyMemberUsers::options($owner, $includeUserIds);
    }

    /**
     * Customer relationships linked to this brand.
     *
     * @return list<array{id: int, related_company_name: string|null, related_company_logo_url: string|null, status: string, owner_company_name: string|null}>
     */
    public function clientsForBrand(Brand $brand): array
    {
        return CompanyRelationship::query()
            ->with(['relatedCompany:id,name,logo', 'ownerCompany:id,name'])
            ->where('brand_id', $brand->id)
            ->where('kind', CompanyRelationshipKind::Customer->value)
            ->orderBy('id')
            ->get()
            ->map(fn (CompanyRelationship $relationship): array => [
                'id' => $relationship->id,
                'related_company_name' => $relationship->relatedCompany?->name,
                'related_company_logo_url' => $relationship->relatedCompany?->logoUrl(),
                'status' => $relationship->status->value,
                'owner_company_name' => $relationship->ownerCompany?->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function toFormData(Brand $brand): array
    {
        $brand->loadMissing('collaborators');

        return [
            'id' => $brand->id,
            'name' => $brand->name,
            'account_manager_id' => $brand->account_manager_id,
            'commercial_manager_id' => $brand->commercial_manager_id,
            'collaborator_ids' => $brand->collaborators->pluck('id')->values()->all(),
            'loyalty_meeting_frequency' => $brand->loyalty_meeting_frequency,
            'is_quality_control_contactable' => $brand->is_quality_control_contactable,
            'send_debt_reminders' => $brand->send_debt_reminders,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toListItem(Brand $brand): array
    {
        return [
            'id' => $brand->id,
            'name' => $brand->name,
            'account_manager_id' => $brand->account_manager_id,
            'account_manager_name' => $brand->accountManager?->name,
            'loyalty_meeting_frequency' => $brand->loyalty_meeting_frequency,
            'clients_count' => (int) ($brand->clients_count ?? 0),
        ];
    }

    /**
     * Seed options for Inertia pages (selected brands only). Full lists load via /select-options/brands.
     *
     * @param  list<int>  $includeIds
     * @return list<array{id: int, label: string}>
     */
    public function brandOptions(?int $includeId = null, array $includeIds = []): array
    {
        $ids = $includeIds;
        if ($includeId !== null) {
            $ids[] = $includeId;
        }

        return $this->optionsByIds($ids);
    }

    /**
     * @param  list<int>  $ids
     * @return list<array{id: int, label: string}>
     */
    public function optionsByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if ($ids === []) {
            return [];
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
     * Lightweight options for async brand pickers.
     *
     * @return list<array{id: int, label: string}>
     */
    public function searchOptions(
        ?string $search = null,
        ?int $includeId = null,
        ?int $limit = 50,
    ): array {
        $needle = trim((string) $search);

        $rows = Brand::query()
            ->when($needle !== '', fn ($query) => $query->where('name', 'like', "%{$needle}%"))
            ->orderBy('name')
            ->when($limit !== null, fn ($query) => $query->limit($limit))
            ->get(['id', 'name'])
            ->map(fn (Brand $brand): array => [
                'id' => $brand->id,
                'label' => $brand->name,
            ])
            ->values()
            ->all();

        if ($includeId !== null && ! collect($rows)->contains(fn (array $row): bool => (int) $row['id'] === $includeId)) {
            $extra = Brand::query()->whereKey($includeId)->first(['id', 'name']);
            if ($extra !== null) {
                array_unshift($rows, [
                    'id' => $extra->id,
                    'label' => $extra->name,
                ]);
            }
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    private function intList(mixed $value): array
    {
        if (is_array($value)) {
            $parts = $value;
        } else {
            $raw = trim((string) $value);

            if ($raw === '') {
                return [];
            }

            $parts = preg_split('/\s*,\s*/', $raw) ?: [];
        }

        return array_values(array_unique(array_filter(
            array_map(static fn ($item): int => (int) $item, $parts),
            static fn (int $id): bool => $id > 0,
        )));
    }
}
