<?php

declare(strict_types=1);

namespace App\Domain\Companies\Services;

use App\Domain\Companies\Support\Coordinates;
use App\Models\Brand;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Country;
use App\Models\Language;
use App\Models\User;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class CompanyService
{
    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null, page?: int|string|null, kind?: string|null, is_active?: string|null, created_from?: string|null, created_to?: string|null}  $filters
     * @return LengthAwarePaginator<int, Company>
     */
    public function paginate(array $filters = [], ?int $perPage = null, ?User $member = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $kind = trim((string) ($filters['kind'] ?? ''));
        $isActive = trim((string) ($filters['is_active'] ?? ''));
        $createdFrom = trim((string) ($filters['created_from'] ?? ''));
        $createdTo = trim((string) ($filters['created_to'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort(
            $filters,
            ['id', 'name', 'tax_id', 'kind', 'is_active', 'created_at', 'member_since'],
            'name',
        );
        $page = max(1, (int) ($filters['page'] ?? request()->integer('page', 1)));
        $orderColumn = $sort === 'member_since' ? 'company_user.created_at' : "companies.{$sort}";

        return Company::query()
            ->select('companies.*')
            ->with(['country', 'brand'])
            ->when($member !== null, function ($query) use ($member): void {
                $query
                    ->join('company_user', function ($join) use ($member): void {
                        $join->on('companies.id', '=', 'company_user.company_id')
                            ->where('company_user.user_id', '=', $member->id)
                            ->where('company_user.is_active', '=', true)
                            ->whereNull('company_user.deleted_at');
                    })
                    ->addSelect('company_user.created_at as member_since');
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner
                        ->where('companies.name', 'like', "%{$search}%")
                        ->orWhere('companies.tradename', 'like', "%{$search}%")
                        ->orWhere('companies.tax_id', 'like', "%{$search}%")
                        ->orWhere('companies.slug', 'like', "%{$search}%");
                });
            })
            ->when($kind !== '', fn ($query) => $query->where('companies.kind', $kind))
            ->when(
                $isActive === '1' || $isActive === '0',
                fn ($query) => $query->where('companies.is_active', $isActive === '1'),
            )
            ->when($createdFrom !== '', fn ($query) => $query->whereDate('companies.created_at', '>=', $createdFrom))
            ->when($createdTo !== '', fn ($query) => $query->whereDate('companies.created_at', '<=', $createdTo))
            ->when(
                $sort === 'member_since' && $member === null,
                fn ($query) => $query->orderBy('companies.created_at', $direction),
                fn ($query) => $query->orderBy($orderColumn, $direction),
            )
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();
    }

    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null, kind?: string|null, is_active?: string|null, created_from?: string|null, created_to?: string|null}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateForWeb(array $filters = [], ?int $perPage = null, ?User $member = null): LengthAwarePaginator
    {
        return $this->paginate($filters, $perPage, $member)
            ->through(fn (Company $company): array => $this->toListItem($company));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $member = null): Company
    {
        return DB::transaction(function () use ($data, $member): Company {
            $payload = $this->attributes($data);
            $payload = $this->applyLogo($payload, null);
            $payload['slug'] = $payload['slug'] ?? Company::uniqueSlugFromName((string) $payload['name']);

            $company = Company::query()->create($payload);

            if ($member !== null) {
                $company->users()->syncWithoutDetaching([
                    $member->id => ['is_active' => true],
                ]);

                if ($member->active_company_id === null) {
                    $member->forceFill(['active_company_id' => $company->id])->save();
                }
            }

            return $company->fresh(['country', 'brand']) ?? $company;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Company $company, array $data): Company
    {
        return DB::transaction(function () use ($company, $data): Company {
            $payload = $this->attributes($data);
            $payload = $this->applyLogo($payload, $company);

            if (isset($payload['slug'])) {
                $payload['slug'] = Str::slug((string) $payload['slug']) ?: $company->slug;
            }

            $company->update($payload);

            return $company->fresh(['country', 'brand']) ?? $company;
        });
    }

    public function delete(Company $company): void
    {
        if ($company->trashed()) {
            return;
        }

        DB::transaction(function () use ($company): void {
            $this->deleteLogoFile($company->logo);
            $company->softDeleteSafely();
        });
    }

    /**
     * @return list<array{id: int, name: string, email: string, is_active: bool}>
     */
    public function members(Company $company): array
    {
        return $company->users()
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.email'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => (bool) $user->pivot->is_active,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function assignableUserOptions(Company $company): array
    {
        $memberIds = $company->users()->pluck('users.id');

        return User::query()
            ->when($memberIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $memberIds))
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'label' => "{$user->name} ({$user->email})",
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, label: string, logo_url: string|null}>
     */
    public function membershipOptionsForUser(User $user): array
    {
        return $user->companies()
            ->wherePivot('is_active', true)
            ->orderBy('companies.name')
            ->get(['companies.id', 'companies.name', 'companies.tax_id', 'companies.logo'])
            ->map(fn (Company $company): array => $company->toSelectOption())
            ->values()
            ->all();
    }

    public function attachUser(Company $company, int $userId): void
    {
        DB::transaction(function () use ($company, $userId): void {
            $membership = CompanyUser::withTrashed()
                ->where('company_id', $company->id)
                ->where('user_id', $userId)
                ->first();

            if ($membership !== null) {
                if ($membership->trashed()) {
                    $membership->restore();
                }

                $membership->forceFill(['is_active' => true])->save();
            } else {
                $company->users()->attach($userId, ['is_active' => true]);
            }

            $user = User::query()->find($userId);

            if ($user !== null && $user->active_company_id === null) {
                $user->forceFill(['active_company_id' => $company->id])->save();
            }
        });
    }

    public function detachUser(Company $company, User $user): void
    {
        DB::transaction(function () use ($company, $user): void {
            $membership = CompanyUser::query()
                ->where('company_id', $company->id)
                ->where('user_id', $user->id)
                ->whereNull('deleted_at')
                ->first();

            if ($membership === null) {
                return;
            }

            $membership->delete();

            if ((int) $user->active_company_id === $company->id) {
                $next = $user->companies()
                    ->wherePivot('is_active', true)
                    ->orderBy('companies.name')
                    ->first();

                $user->forceFill([
                    'active_company_id' => $next?->id,
                ])->save();
            }
        });
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function countryOptions(): array
    {
        return Country::query()
            ->orderBy('name')
            ->get(['id', 'name', 'iso_code'])
            ->map(fn (Country $country): array => [
                'id' => $country->id,
                'label' => $country->iso_code
                    ? "{$country->name} ({$country->iso_code})"
                    : $country->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function brandOptions(): array
    {
        return Brand::query()
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
     * Seed options for Inertia pages (selected company only). Full lists load via /companies/options.
     *
     * @return list<array{id: int, label: string, logo_url: string|null}>
     */
    public function companyOptions(?int $exceptId = null, ?int $includeId = null): array
    {
        unset($exceptId);

        return $includeId !== null ? $this->optionsByIds([$includeId]) : [];
    }

    /**
     * Lightweight options for async company pickers. Pass `$limit` null only for rare full dumps.
     *
     * @param  list<int>|null  $onlyIds
     * @return list<array{id: int, label: string, logo_url: string|null}>
     */
    public function searchOptions(
        ?string $search = null,
        ?int $exceptId = null,
        ?int $includeId = null,
        ?array $onlyIds = null,
        ?int $limit = 50,
        string $labelStyle = 'tax_id',
    ): array {
        if ($onlyIds !== null && $onlyIds === []) {
            return $includeId !== null
                ? $this->optionsByIds([$includeId], $labelStyle)
                : [];
        }

        $needle = trim((string) $search);

        $query = Company::query()
            ->when($exceptId !== null, fn ($builder) => $builder->where('id', '!=', $exceptId))
            ->when($onlyIds !== null, fn ($builder) => $builder->whereIn('id', $onlyIds))
            ->where(function ($builder) use ($includeId): void {
                $builder->where('is_active', true);

                if ($includeId !== null) {
                    $builder->orWhereKey($includeId);
                }
            })
            ->when($needle !== '', function ($builder) use ($needle): void {
                $builder->where(function ($inner) use ($needle): void {
                    $inner
                        ->where('name', 'like', "%{$needle}%")
                        ->orWhere('tradename', 'like', "%{$needle}%")
                        ->orWhere('tax_id', 'like', "%{$needle}%")
                        ->orWhere('slug', 'like', "%{$needle}%");
                });
            })
            ->orderBy('name')
            ->select(['id', 'name', 'tradename', 'tax_id', 'logo']);

        if ($limit !== null) {
            $query->limit(max(1, min($limit, 100)));
        }

        $rows = $query
            ->get()
            ->map(fn (Company $company): array => $company->toSelectOption($labelStyle))
            ->values()
            ->all();

        if ($includeId !== null && ! collect($rows)->contains(fn (array $row): bool => (int) $row['id'] === $includeId)) {
            $extra = Company::query()->whereKey($includeId)->first(['id', 'name', 'tradename', 'tax_id', 'logo']);
            if ($extra !== null) {
                array_unshift($rows, $extra->toSelectOption($labelStyle));
            }
        }

        return $rows;
    }

    /**
     * @param  list<int>  $ids
     * @return list<array{id: int, label: string, logo_url: string|null}>
     */
    public function optionsByIds(array $ids, string $labelStyle = 'tax_id'): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if ($ids === []) {
            return [];
        }

        return Company::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name', 'tradename', 'tax_id', 'logo'])
            ->map(fn (Company $company): array => $company->toSelectOption($labelStyle))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function toFormData(Company $company): array
    {
        $company->loadMissing(['country', 'brand']);

        return [
            'id' => $company->id,
            'name' => $company->name,
            'tradename' => $company->tradename,
            'slug' => $company->slug,
            'tax_id' => $company->tax_id,
            'kind' => $company->kind->value,
            'country_id' => $company->country_id,
            'residence_country_id' => $company->residence_country_id,
            'person_type' => $company->person_type?->value,
            'email' => $company->email,
            'phone' => $company->phone,
            'website' => $company->website,
            'address_line_1' => $company->address_line_1,
            'address_line_2' => $company->address_line_2,
            'city' => $company->city,
            'province_id' => $company->province_id,
            'postal_code' => $company->postal_code,
            'employee_count' => $company->employee_count,
            'is_active' => $company->is_active,
            'logo_url' => $company->logoUrl(),
            'brand_id' => $company->brand_id,
            'language_id' => $company->language_id,
            'latitude' => Coordinates::format($company->latitude),
            'longitude' => Coordinates::format($company->longitude),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toListItem(Company $company): array
    {
        $memberSince = $company->getAttribute('member_since');

        return [
            'id' => $company->id,
            'name' => $company->name,
            'tradename' => $company->tradename,
            'tax_id' => $company->tax_id,
            'kind' => $company->kind->value,
            'country_name' => $company->country?->name,
            'logo_url' => $company->logoUrl(),
            'is_active' => $company->is_active,
            'created_at' => $company->created_at?->toIso8601String(),
            'member_since' => $memberSince
                ? Carbon::parse($memberSince)->toIso8601String()
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $nullable = [
            'tradename', 'slug', 'tax_id', 'country_id', 'residence_country_id',
            'person_type', 'email', 'phone', 'website', 'address_line_1',
            'address_line_2', 'city', 'province_id', 'postal_code', 'employee_count',
            'brand_id', 'language_id', 'latitude', 'longitude',
        ];

        foreach ($nullable as $key) {
            if (array_key_exists($key, $data) && $data[$key] === '') {
                $data[$key] = null;
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function applyLogo(array $payload, ?Company $company): array
    {
        $removeLogo = (bool) ($payload['remove_logo'] ?? false);
        unset($payload['remove_logo']);

        $file = $payload['logo'] ?? null;
        unset($payload['logo']);

        if ($file instanceof UploadedFile) {
            $this->deleteLogoFile($company?->logo);
            $payload['logo'] = $file->store('company-logos', 'public');

            return $payload;
        }

        if ($removeLogo) {
            $this->deleteLogoFile($company?->logo);
            $payload['logo'] = null;
        }

        return $payload;
    }

    private function deleteLogoFile(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
