<?php

declare(strict_types=1);

namespace App\Domain\Config\Banks\Services;

use App\Models\Bank;
use App\Models\Country;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class BankService
{
    /**
     * @param  array{search?: string|null, status?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, Bank>
     */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort($filters, ['id', 'name', 'country_id', 'swift_bic', 'is_active'], 'name');

        return Bank::query()
            ->with('country')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('legal_name', 'like', "%{$search}%")
                        ->orWhere('swift_bic', 'like', "%{$search}%")
                        ->orWhere('national_bank_code', 'like', "%{$search}%")
                        ->orWhereHas('country', fn ($country) => $country->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array{search?: string|null, status?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateForWeb(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        return $this->paginate($filters, $perPage)
            ->through(fn (Bank $bank): array => $this->toListItem($bank));
    }

    /**
     * @param  array{
     *     name: string,
     *     legal_name?: string|null,
     *     country_id: int,
     *     swift_bic?: string|null,
     *     national_bank_code?: string|null,
     *     lei?: string|null,
     *     supervisor_code?: string|null,
     *     website?: string|null,
     *     is_active?: bool
     * }  $data
     */
    public function create(array $data): Bank
    {
        return DB::transaction(function () use ($data): Bank {
            return Bank::query()->create([
                'name' => $data['name'],
                'legal_name' => $data['legal_name'] ?: null,
                'country_id' => $data['country_id'],
                'swift_bic' => $data['swift_bic'] ?: null,
                'national_bank_code' => $data['national_bank_code'] ?: null,
                'lei' => $data['lei'] ?: null,
                'supervisor_code' => $data['supervisor_code'] ?: null,
                'website' => $data['website'] ?: null,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);
        });
    }

    /**
     * @param  array{
     *     name: string,
     *     legal_name?: string|null,
     *     country_id: int,
     *     swift_bic?: string|null,
     *     national_bank_code?: string|null,
     *     lei?: string|null,
     *     supervisor_code?: string|null,
     *     website?: string|null,
     *     is_active?: bool
     * }  $data
     */
    public function update(Bank $bank, array $data): Bank
    {
        return DB::transaction(function () use ($bank, $data): Bank {
            $bank->update([
                'name' => $data['name'],
                'legal_name' => $data['legal_name'] ?: null,
                'country_id' => $data['country_id'],
                'swift_bic' => $data['swift_bic'] ?: null,
                'national_bank_code' => $data['national_bank_code'] ?: null,
                'lei' => $data['lei'] ?: null,
                'supervisor_code' => $data['supervisor_code'] ?: null,
                'website' => $data['website'] ?: null,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            return $bank->fresh();
        });
    }

    public function delete(Bank $bank): void
    {
        if ($bank->trashed()) {
            return;
        }

        DB::transaction(function () use ($bank): void {
            $bank->softDeleteSafely();
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
     * @return array{
     *     id: int,
     *     name: string,
     *     legal_name: string|null,
     *     country_id: int|null,
     *     swift_bic: string|null,
     *     national_bank_code: string|null,
     *     lei: string|null,
     *     supervisor_code: string|null,
     *     website: string|null,
     *     is_active: bool
     * }
     */
    public function toFormData(Bank $bank): array
    {
        return [
            'id' => $bank->id,
            'name' => $bank->name,
            'legal_name' => $bank->legal_name,
            'country_id' => $bank->country_id,
            'swift_bic' => $bank->swift_bic,
            'national_bank_code' => $bank->national_bank_code,
            'lei' => $bank->lei,
            'supervisor_code' => $bank->supervisor_code,
            'website' => $bank->website,
            'is_active' => $bank->is_active,
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     legal_name: string|null,
     *     country_id: int|null,
     *     country_name: string|null,
     *     swift_bic: string|null,
     *     national_bank_code: string|null,
     *     is_active: bool,
     *     created_at: string|null
     * }
     */
    public function toListItem(Bank $bank): array
    {
        return [
            'id' => $bank->id,
            'name' => $bank->name,
            'legal_name' => $bank->legal_name,
            'country_id' => $bank->country_id,
            'country_name' => $bank->country?->name,
            'swift_bic' => $bank->swift_bic,
            'national_bank_code' => $bank->national_bank_code,
            'is_active' => $bank->is_active,
            'created_at' => $bank->created_at?->toIso8601String(),
        ];
    }
}
