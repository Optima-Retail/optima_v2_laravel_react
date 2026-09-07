<?php

declare(strict_types=1);

namespace App\Domain\Companies\Services;

use App\Domain\Companies\Enums\CompanyKind;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\User;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CompanyRelationshipService
{
    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null, page?: int|string|null, kind?: string|null, kinds?: list<string>|null}  $filters
     * @return LengthAwarePaginator<int, CompanyRelationship>
     */
    public function paginateForOwner(Company $owner, array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $kind = trim((string) ($filters['kind'] ?? ''));
        /** @var list<string> $kinds */
        $kinds = array_values(array_filter(
            array_map('strval', $filters['kinds'] ?? []),
            static fn (string $value): bool => $value !== '',
        ));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort($filters, ['id', 'kind', 'status', 'created_at'], 'id', 'desc');
        $page = max(1, (int) ($filters['page'] ?? request()->integer('page', 1)));

        return CompanyRelationship::query()
            ->with(['relatedCompany', 'brand'])
            ->where('owner_company_id', $owner->id)
            ->when($kinds !== [], fn ($query) => $query->whereIn('kind', $kinds))
            ->when($kind !== '', fn ($query) => $query->where('kind', $kind))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner
                        ->where('owner_reference', 'like', "%{$search}%")
                        ->orWhere('related_reference', 'like', "%{$search}%")
                        ->orWhere('external_code', 'like', "%{$search}%")
                        ->orWhereHas('relatedCompany', fn ($companies) => $companies
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('tax_id', 'like', "%{$search}%"));
                });
            })
            ->orderBy($sort, $direction)
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();
    }

    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null, page?: int|string|null, kind?: string|null, kinds?: list<string>|null}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateForWeb(Company $owner, array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        return $this->paginateForOwner($owner, $filters, $perPage)
            ->through(fn (CompanyRelationship $relationship): array => $this->toListItem($relationship));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Company $owner, array $data, User $actor): CompanyRelationship
    {
        return DB::transaction(function () use ($owner, $data, $actor): CompanyRelationship {
            $relatedId = $this->resolveRelatedCompanyId($data);

            if ($relatedId === $owner->id) {
                throw ValidationException::withMessages([
                    'related_company_id' => 'A company cannot have a relationship with itself.',
                ]);
            }

            $kind = (string) ($data['kind'] ?? '');

            $duplicate = CompanyRelationship::query()
                ->where('owner_company_id', $owner->id)
                ->where('related_company_id', $relatedId)
                ->where('kind', $kind)
                ->where('deleted_token', '')
                ->exists();

            if ($duplicate) {
                $field = (($data['related_mode'] ?? 'existing') === 'new')
                    ? 'related_company.name'
                    : 'related_company_id';

                throw ValidationException::withMessages([
                    $field => 'This relationship already exists for the selected company and type.',
                ]);
            }

            $payload = $this->attributes($data);
            $payload['related_company_id'] = $relatedId;

            return CompanyRelationship::query()->create([
                ...$payload,
                'owner_company_id' => $owner->id,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ])->load(['relatedCompany', 'brand']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveRelatedCompanyId(array $data): int
    {
        $mode = (string) ($data['related_mode'] ?? 'existing');

        if ($mode === 'new') {
            /** @var array<string, mixed> $related */
            $related = is_array($data['related_company'] ?? null) ? $data['related_company'] : [];

            $company = app(CompanyService::class)->create([
                'name' => (string) ($related['name'] ?? ''),
                'tradename' => $related['tradename'] ?? null,
                'tax_id' => $related['tax_id'] ?? null,
                'kind' => CompanyKind::Party->value,
                'email' => $related['email'] ?? null,
                'phone' => $related['phone'] ?? null,
                'is_active' => true,
            ]);

            return $company->id;
        }

        return (int) ($data['related_company_id'] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CompanyRelationship $relationship, array $data, User $actor): CompanyRelationship
    {
        $relatedId = (int) ($data['related_company_id'] ?? $relationship->related_company_id);

        if ($relatedId === $relationship->owner_company_id) {
            throw ValidationException::withMessages([
                'related_company_id' => 'A company cannot have a relationship with itself.',
            ]);
        }

        return DB::transaction(function () use ($relationship, $data, $actor): CompanyRelationship {
            $relationship->update([
                ...$this->attributes($data),
                'updated_by' => $actor->id,
            ]);

            return $relationship->fresh(['relatedCompany', 'brand']) ?? $relationship;
        });
    }

    public function delete(CompanyRelationship $relationship): void
    {
        if ($relationship->trashed()) {
            return;
        }

        DB::transaction(function () use ($relationship): void {
            $relationship->softDeleteSafely();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function toFormData(CompanyRelationship $relationship): array
    {
        $relationship->loadMissing(['relatedCompany', 'brand']);

        return [
            'id' => $relationship->id,
            'owner_company_id' => $relationship->owner_company_id,
            'related_company_id' => $relationship->related_company_id,
            'kind' => $relationship->kind->value,
            'status' => $relationship->status->value,
            'classification' => $relationship->classification->value,
            'owner_reference' => $relationship->owner_reference,
            'related_reference' => $relationship->related_reference,
            'brand_id' => $relationship->brand_id,
            'external_code' => $relationship->external_code,
            'notes' => $relationship->notes,
            'starts_at' => $relationship->starts_at?->toDateString(),
            'ends_at' => $relationship->ends_at?->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toListItem(CompanyRelationship $relationship): array
    {
        return [
            'id' => $relationship->id,
            'related_company_id' => $relationship->related_company_id,
            'related_company_name' => $relationship->relatedCompany?->name,
            'kind' => $relationship->kind->value,
            'status' => $relationship->status->value,
            'classification' => $relationship->classification->value,
            'brand_name' => $relationship->brand?->name,
            'owner_reference' => $relationship->owner_reference,
            'created_at' => $relationship->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        unset($data['related_mode'], $data['related_company'], $data['owner_company_id']);

        foreach (['owner_reference', 'related_reference', 'brand_id', 'external_code', 'notes', 'starts_at', 'ends_at'] as $key) {
            if (array_key_exists($key, $data) && $data[$key] === '') {
                $data[$key] = null;
            }
        }

        return $data;
    }
}
