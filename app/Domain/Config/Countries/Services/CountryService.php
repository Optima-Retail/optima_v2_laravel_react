<?php

declare(strict_types=1);

namespace App\Domain\Config\Countries\Services;

use App\Models\Country;
use App\Models\Timezone;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class CountryService
{
    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, Country>
     */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort(
            $filters,
            ['id', 'name', 'iso_code', 'timezone_id', 'provinces_count'],
            'name',
        );

        return Country::query()
            ->with('timezone')
            ->withCount('provinces')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('iso_code', 'like', "%{$search}%")
                        ->orWhereHas('timezone', function ($timezoneQuery) use ($search): void {
                            $timezoneQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('timezone', 'like', "%{$search}%");
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
            ->through(fn (Country $country): array => $this->toListItem($country));
    }

    /**
     * @param  array{name: string, iso_code?: string|null, timezone_id?: int|null}  $data
     */
    public function create(array $data): Country
    {
        return DB::transaction(function () use ($data): Country {
            return Country::query()->create([
                'name' => $data['name'],
                'iso_code' => $this->normalizeIso($data['iso_code'] ?? null),
                'timezone_id' => $data['timezone_id'] ?? null,
            ]);
        });
    }

    /**
     * @param  array{name: string, iso_code?: string|null, timezone_id?: int|null}  $data
     */
    public function update(Country $country, array $data): Country
    {
        return DB::transaction(function () use ($country, $data): Country {
            $country->update([
                'name' => $data['name'],
                'iso_code' => $this->normalizeIso($data['iso_code'] ?? null),
                'timezone_id' => $data['timezone_id'] ?? null,
            ]);

            return $country->fresh(['timezone']);
        });
    }

    public function delete(Country $country): void
    {
        if ($country->trashed()) {
            return;
        }

        DB::transaction(function () use ($country): void {
            $country->delete();
        });
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function timezoneOptions(): array
    {
        /** @var Collection<int, Timezone> $timezones */
        $timezones = Timezone::query()
            ->orderBy('name')
            ->get(['id', 'name', 'timezone']);

        return $timezones
            ->map(fn (Timezone $timezone): array => [
                'id' => $timezone->id,
                'label' => $timezone->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, name: string, iso_code: string|null, timezone_id: int|null}
     */
    public function toFormData(Country $country): array
    {
        return [
            'id' => $country->id,
            'name' => $country->name,
            'iso_code' => $country->iso_code,
            'timezone_id' => $country->timezone_id,
        ];
    }

    /**
     * @return array{id: int, name: string, iso_code: string|null, timezone_id: int|null, timezone_name: string|null, provinces_count: int, created_at: string|null}
     */
    public function toListItem(Country $country): array
    {
        return [
            'id' => $country->id,
            'name' => $country->name,
            'iso_code' => $country->iso_code,
            'timezone_id' => $country->timezone_id,
            'timezone_name' => $country->timezone?->name,
            'provinces_count' => (int) ($country->provinces_count ?? $country->provinces()->count()),
            'created_at' => $country->created_at?->toIso8601String(),
        ];
    }

    private function normalizeIso(mixed $iso): ?string
    {
        $value = strtoupper(trim((string) $iso));

        return $value === '' ? null : $value;
    }
}
