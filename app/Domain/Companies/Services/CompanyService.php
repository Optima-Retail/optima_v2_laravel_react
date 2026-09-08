<?php

declare(strict_types=1);

namespace App\Domain\Companies\Services;

use App\Models\Brand;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Country;
use App\Models\Language;
use App\Models\User;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CompanyService
{
    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null, page?: int|string|null, kind?: string|null}  $filters
     * @return LengthAwarePaginator<int, Company>
     */
    public function paginate(array $filters = [], ?int $perPage = null, ?User $member = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $kind = trim((string) ($filters['kind'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort($filters, ['id', 'name', 'tax_id', 'kind', 'created_at'], 'name');
        $page = max(1, (int) ($filters['page'] ?? request()->integer('page', 1)));

        return Company::query()
            ->with(['country', 'brand'])
            ->when($member !== null, function ($query) use ($member): void {
                $query->whereIn('id', $member->companies()
                    ->wherePivot('is_active', true)
                    ->select('companies.id'));
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('tradename', 'like', "%{$search}%")
                        ->orWhere('tax_id', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when($kind !== '', fn ($query) => $query->where('kind', $kind))
            ->orderBy($sort, $direction)
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();
    }

    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null, kind?: string|null}  $filters
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
     * @return list<array{id: int, label: string}>
     */
    public function membershipOptionsForUser(User $user): array
    {
        return $user->companies()
            ->wherePivot('is_active', true)
            ->orderBy('companies.name')
            ->get(['companies.id', 'companies.name', 'companies.tax_id'])
            ->map(fn (Company $company): array => [
                'id' => $company->id,
                'label' => $company->tax_id
                    ? "{$company->name} ({$company->tax_id})"
                    : $company->name,
            ])
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
     * @return list<array{id: int, label: string}>
     */
    public function companyOptions(?int $exceptId = null): array
    {
        return Company::query()
            ->when($exceptId !== null, fn ($query) => $query->where('id', '!=', $exceptId))
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
            'province' => $company->province,
            'postal_code' => $company->postal_code,
            'employee_count' => $company->employee_count,
            'is_active' => $company->is_active,
            'brand_id' => $company->brand_id,
            'language_id' => $company->language_id,
            'latitude' => $company->latitude,
            'longitude' => $company->longitude,
            'legacy_erp_id' => $company->legacy_erp_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toListItem(Company $company): array
    {
        return [
            'id' => $company->id,
            'name' => $company->name,
            'tradename' => $company->tradename,
            'tax_id' => $company->tax_id,
            'kind' => $company->kind->value,
            'country_name' => $company->country?->name,
            'is_active' => $company->is_active,
            'created_at' => $company->created_at?->toIso8601String(),
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
            'address_line_2', 'city', 'province', 'postal_code', 'employee_count',
            'logo', 'brand_id', 'language_id', 'latitude', 'longitude', 'legacy_erp_id',
        ];

        foreach ($nullable as $key) {
            if (array_key_exists($key, $data) && $data[$key] === '') {
                $data[$key] = null;
            }
        }

        return $data;
    }
}
