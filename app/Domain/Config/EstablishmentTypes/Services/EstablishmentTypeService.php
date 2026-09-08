<?php

declare(strict_types=1);

namespace App\Domain\Config\EstablishmentTypes\Services;

use App\Models\EstablishmentType;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class EstablishmentTypeService
{
    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, EstablishmentType>
     */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort(
            $filters,
            ['id', 'name', 'code', 'health_and_safety_delay_days'],
            'name',
        );

        return EstablishmentType::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
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
            ->through(fn (EstablishmentType $establishmentType): array => $this->toListItem($establishmentType));
    }

    /**
     * @param  array{name: string, code: string, health_and_safety_delay_days: int}  $data
     */
    public function create(array $data): EstablishmentType
    {
        return DB::transaction(function () use ($data): EstablishmentType {
            return EstablishmentType::query()->create([
                'name' => $data['name'],
                'code' => strtolower($data['code']),
                'health_and_safety_delay_days' => $data['health_and_safety_delay_days'],
            ]);
        });
    }

    /**
     * @param  array{name: string, code: string, health_and_safety_delay_days: int}  $data
     */
    public function update(EstablishmentType $establishmentType, array $data): EstablishmentType
    {
        return DB::transaction(function () use ($establishmentType, $data): EstablishmentType {
            $establishmentType->update([
                'name' => $data['name'],
                'code' => strtolower($data['code']),
                'health_and_safety_delay_days' => $data['health_and_safety_delay_days'],
            ]);

            return $establishmentType->fresh() ?? $establishmentType;
        });
    }

    public function delete(EstablishmentType $establishmentType): void
    {
        if ($establishmentType->trashed()) {
            return;
        }

        DB::transaction(function () use ($establishmentType): void {
            $establishmentType->softDeleteSafely();
        });
    }

    /**
     * @return array{id: int, name: string, code: string, health_and_safety_delay_days: int}
     */
    public function toFormData(EstablishmentType $establishmentType): array
    {
        return [
            'id' => $establishmentType->id,
            'name' => $establishmentType->name,
            'code' => $establishmentType->code,
            'health_and_safety_delay_days' => $establishmentType->health_and_safety_delay_days,
        ];
    }

    /**
     * @return array{id: int, name: string, code: string, health_and_safety_delay_days: int, created_at: string|null}
     */
    public function toListItem(EstablishmentType $establishmentType): array
    {
        return [
            'id' => $establishmentType->id,
            'name' => $establishmentType->name,
            'code' => $establishmentType->code,
            'health_and_safety_delay_days' => $establishmentType->health_and_safety_delay_days,
            'created_at' => $establishmentType->created_at?->toIso8601String(),
        ];
    }
}
