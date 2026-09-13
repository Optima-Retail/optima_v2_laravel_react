<?php

declare(strict_types=1);

namespace App\Domain\Config\CostCenters\Services;

use App\Models\CostCenter;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class CostCenterService
{
    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, CostCenter>
     */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort($filters, ['id', 'name', 'code'], 'name');

        return CostCenter::query()
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
            ->through(fn (CostCenter $costCenter): array => $this->toListItem($costCenter));
    }

    /**
     * @param  array{name: string, code: string}  $data
     */
    public function create(array $data): CostCenter
    {
        return DB::transaction(function () use ($data): CostCenter {
            return CostCenter::query()->create([
                'name' => $data['name'],
                'code' => strtoupper($data['code']),
            ]);
        });
    }

    /**
     * @param  array{name: string, code: string}  $data
     */
    public function update(CostCenter $costCenter, array $data): CostCenter
    {
        return DB::transaction(function () use ($costCenter, $data): CostCenter {
            $costCenter->update([
                'name' => $data['name'],
                'code' => strtoupper($data['code']),
            ]);

            return $costCenter->fresh();
        });
    }

    public function delete(CostCenter $costCenter): void
    {
        if ($costCenter->trashed()) {
            return;
        }

        DB::transaction(function () use ($costCenter): void {
            $costCenter->softDeleteSafely();
        });
    }

    /**
     * @return array{id: int, name: string, code: string}
     */
    public function toFormData(CostCenter $costCenter): array
    {
        return [
            'id' => $costCenter->id,
            'name' => $costCenter->name,
            'code' => $costCenter->code,
        ];
    }

    /**
     * @return array{id: int, name: string, code: string, created_at: string|null}
     */
    public function toListItem(CostCenter $costCenter): array
    {
        return [
            'id' => $costCenter->id,
            'name' => $costCenter->name,
            'code' => $costCenter->code,
            'created_at' => $costCenter->created_at?->toIso8601String(),
        ];
    }
}
