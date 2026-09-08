<?php

declare(strict_types=1);

namespace App\Domain\Config\Provinces\Services;

use App\Models\Country;
use App\Models\Province;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ProvinceService
{
    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, Province>
     */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort($filters, ['id', 'name', 'code', 'country_id'], 'name');

        return Province::query()
            ->with('country')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhereHas('country', function ($countryQuery) use ($search): void {
                            $countryQuery->where('name', 'like', "%{$search}%");
                        });
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
            ->through(fn (Province $province): array => $this->toListItem($province));
    }

    /**
     * @param  array{name: string, code?: string|null, country_id: int}  $data
     */
    public function create(array $data): Province
    {
        return DB::transaction(function () use ($data): Province {
            return Province::query()->create([
                'name' => $data['name'],
                'code' => $this->normalizeCode($data['code'] ?? null),
                'country_id' => $data['country_id'],
            ]);
        });
    }

    /**
     * @param  array{name: string, code?: string|null, country_id: int}  $data
     */
    public function update(Province $province, array $data): Province
    {
        return DB::transaction(function () use ($province, $data): Province {
            $province->update([
                'name' => $data['name'],
                'code' => $this->normalizeCode($data['code'] ?? null),
                'country_id' => $data['country_id'],
            ]);

            return $province->fresh(['country']);
        });
    }

    public function delete(Province $province): void
    {
        if ($province->trashed()) {
            return;
        }

        DB::transaction(function () use ($province): void {
            $province->delete();
        });
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function countryOptions(): array
    {
        /** @var Collection<int, Country> $countries */
        $countries = Country::query()
            ->orderBy('name')
            ->get(['id', 'name', 'iso_code']);

        return $countries
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
     * @return list<array{id: int, name: string, code: string|null, country_id: int}>
     */
    public function forCountry(Country $country): array
    {
        return Province::query()
            ->where('country_id', $country->id)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'country_id'])
            ->map(fn (Province $province): array => [
                'id' => $province->id,
                'name' => $province->name,
                'code' => $province->code,
                'country_id' => $province->country_id,
            ])
            ->values()
            ->all();
    }

    /**
     * Replace the country's province set with the given rows (create / update / soft-delete).
     *
     * @param  list<array{id?: int|null, name: string, code?: string|null}>  $rows
     * @return list<array{id: int, name: string, code: string|null, country_id: int}>
     */
    public function syncForCountry(Country $country, array $rows): array
    {
        return DB::transaction(function () use ($country, $rows): array {
            $keepIds = [];

            foreach ($rows as $row) {
                $name = trim((string) ($row['name'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $payload = [
                    'name' => $name,
                    'code' => $this->normalizeCode($row['code'] ?? null),
                    'country_id' => $country->id,
                ];

                $id = isset($row['id']) ? (int) $row['id'] : 0;

                if ($id > 0) {
                    $province = Province::query()
                        ->where('country_id', $country->id)
                        ->whereKey($id)
                        ->firstOrFail();
                    $province->update($payload);
                    $keepIds[] = $province->id;

                    continue;
                }

                $created = Province::query()->create($payload);
                $keepIds[] = $created->id;
            }

            Province::query()
                ->where('country_id', $country->id)
                ->when(
                    $keepIds === [],
                    fn ($query) => $query,
                    fn ($query) => $query->whereNotIn('id', $keepIds),
                )
                ->get()
                ->each(fn (Province $province) => $this->delete($province));

            return $this->forCountry($country);
        });
    }

    /**
     * @return list<array{id: int, label: string, country_id: int}>
     */
    public function options(?int $countryId = null): array
    {
        /** @var Collection<int, Province> $provinces */
        $provinces = Province::query()
            ->when($countryId !== null, fn ($query) => $query->where('country_id', $countryId))
            ->orderBy('name')
            ->get(['id', 'name', 'country_id']);

        return $provinces
            ->map(fn (Province $province): array => [
                'id' => $province->id,
                'label' => $province->name,
                'country_id' => $province->country_id,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, name: string, code: string|null, country_id: int}
     */
    public function toFormData(Province $province): array
    {
        return [
            'id' => $province->id,
            'name' => $province->name,
            'code' => $province->code,
            'country_id' => $province->country_id,
        ];
    }

    /**
     * @return array{id: int, name: string, code: string|null, country_id: int, country_name: string|null, created_at: string|null}
     */
    public function toListItem(Province $province): array
    {
        return [
            'id' => $province->id,
            'name' => $province->name,
            'code' => $province->code,
            'country_id' => $province->country_id,
            'country_name' => $province->country?->name,
            'created_at' => $province->created_at?->toIso8601String(),
        ];
    }

    private function normalizeCode(mixed $code): ?string
    {
        $value = trim((string) $code);

        return $value === '' ? null : $value;
    }
}
