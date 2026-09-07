<?php

declare(strict_types=1);

namespace App\Domain\Config\Timezones\Services;

use App\Models\Timezone;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class TimezoneService
{
    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, Timezone>
     */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort($filters, ['id', 'name', 'timezone'], 'name');

        return Timezone::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('timezone', 'like', "%{$search}%");
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
            ->through(fn (Timezone $timezone): array => $this->toListItem($timezone));
    }

    /**
     * @param  array{name: string, timezone: string}  $data
     */
    public function create(array $data): Timezone
    {
        return DB::transaction(function () use ($data): Timezone {
            return Timezone::query()->create([
                'name' => $data['name'],
                'timezone' => $data['timezone'],
            ]);
        });
    }

    /**
     * @param  array{name: string, timezone: string}  $data
     */
    public function update(Timezone $timezone, array $data): Timezone
    {
        return DB::transaction(function () use ($timezone, $data): Timezone {
            $timezone->update([
                'name' => $data['name'],
                'timezone' => $data['timezone'],
            ]);

            return $timezone->fresh();
        });
    }

    public function delete(Timezone $timezone): void
    {
        if ($timezone->trashed()) {
            return;
        }

        DB::transaction(function () use ($timezone): void {
            $timezone->softDeleteSafely();
        });
    }

    /**
     * @return array{id: int, name: string, timezone: string}
     */
    public function toFormData(Timezone $timezone): array
    {
        return [
            'id' => $timezone->id,
            'name' => $timezone->name,
            'timezone' => $timezone->timezone,
        ];
    }

    /**
     * @return array{id: int, name: string, timezone: string, created_at: string|null}
     */
    public function toListItem(Timezone $timezone): array
    {
        return [
            'id' => $timezone->id,
            'name' => $timezone->name,
            'timezone' => $timezone->timezone,
            'created_at' => $timezone->created_at?->toIso8601String(),
        ];
    }
}
