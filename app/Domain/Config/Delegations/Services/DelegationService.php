<?php

declare(strict_types=1);

namespace App\Domain\Config\Delegations\Services;

use App\Models\Company;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Delegation;
use App\Models\Series;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class DelegationService
{
    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, Delegation>
     */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort(
            $filters,
            ['id', 'name', 'tax_id', 'company_id', 'created_at'],
            'name',
        );

        return Delegation::query()
            ->with(['company', 'currency', 'country', 'series'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('tax_id', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%")
                        ->orWhereHas('company', fn ($companies) => $companies->where('name', 'like', "%{$search}%"));
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
    public function paginateForWeb(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        return $this->paginate($filters, $perPage)
            ->through(fn (Delegation $delegation): array => $this->toListItem($delegation));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Delegation
    {
        return DB::transaction(function () use ($data): Delegation {
            return Delegation::query()->create($this->attributes($data))->load(['company', 'currency', 'country', 'series']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Delegation $delegation, array $data): Delegation
    {
        return DB::transaction(function () use ($delegation, $data): Delegation {
            $delegation->update($this->attributes($data));

            return $delegation->fresh(['company', 'currency', 'country', 'series']) ?? $delegation;
        });
    }

    public function delete(Delegation $delegation): void
    {
        if ($delegation->trashed()) {
            return;
        }

        DB::transaction(function () use ($delegation): void {
            $delegation->softDeleteSafely();
        });
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function companyOptions(): array
    {
        return Company::query()
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
     * @return list<array{id: int, label: string}>
     */
    public function currencyOptions(): array
    {
        return Currency::query()
            ->orderBy('code')
            ->get(['id', 'name', 'code'])
            ->map(fn (Currency $currency): array => [
                'id' => $currency->id,
                'label' => "{$currency->code} — {$currency->name}",
            ])
            ->values()
            ->all();
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
    public function seriesOptions(): array
    {
        return Series::query()
            ->orderBy('key')
            ->get(['id', 'key'])
            ->map(fn (Series $series): array => [
                'id' => $series->id,
                'label' => $series->key,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function options(): array
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
    public function toFormData(Delegation $delegation): array
    {
        return [
            'id' => $delegation->id,
            'name' => $delegation->name,
            'tax_id' => $delegation->tax_id,
            'company_id' => $delegation->company_id,
            'address' => $delegation->address,
            'currency_id' => $delegation->currency_id,
            'country_id' => $delegation->country_id,
            'series_id' => $delegation->series_id,
            'cost_includes_vat' => $delegation->cost_includes_vat,
            'recovers_vat' => $delegation->recovers_vat,
            'billing_info' => $delegation->billing_info,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toListItem(Delegation $delegation): array
    {
        return [
            'id' => $delegation->id,
            'name' => $delegation->name,
            'tax_id' => $delegation->tax_id,
            'company_name' => $delegation->company?->name,
            'currency_code' => $delegation->currency?->code,
            'country_name' => $delegation->country?->name,
            'series_key' => $delegation->series?->key,
            'created_at' => $delegation->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        foreach (['tax_id', 'company_id', 'address', 'currency_id', 'country_id', 'series_id', 'billing_info'] as $key) {
            if (array_key_exists($key, $data) && $data[$key] === '') {
                $data[$key] = null;
            }
        }

        if (array_key_exists('billing_info', $data) && is_string($data['billing_info'])) {
            $decoded = json_decode($data['billing_info'], true);
            $data['billing_info'] = is_array($decoded) ? $decoded : null;
        }

        return $data;
    }
}
