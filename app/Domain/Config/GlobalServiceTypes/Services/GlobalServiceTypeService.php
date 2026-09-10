<?php

declare(strict_types=1);

namespace App\Domain\Config\GlobalServiceTypes\Services;

use App\Models\GlobalServiceType;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class GlobalServiceTypeService
{
    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, GlobalServiceType>
     */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort($filters, ['id', 'name', 'code'], 'name');

        return GlobalServiceType::query()
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
            ->through(fn (GlobalServiceType $type): array => $this->toListItem($type));
    }

    /**
     * @param  array{name: string, code?: string|null, color?: string|null}  $data
     */
    public function create(array $data): GlobalServiceType
    {
        return DB::transaction(function () use ($data): GlobalServiceType {
            return GlobalServiceType::query()->create([
                'name' => $data['name'],
                'code' => $data['code'] ?: null,
                'color' => $data['color'] ?: null,
            ]);
        });
    }

    /**
     * @param  array{name: string, code?: string|null, color?: string|null}  $data
     */
    public function update(GlobalServiceType $type, array $data): GlobalServiceType
    {
        return DB::transaction(function () use ($type, $data): GlobalServiceType {
            $type->update([
                'name' => $data['name'],
                'code' => $data['code'] ?: null,
                'color' => $data['color'] ?: null,
            ]);

            return $type->fresh();
        });
    }

    public function delete(GlobalServiceType $type): void
    {
        if ($type->trashed()) {
            return;
        }

        DB::transaction(function () use ($type): void {
            $type->softDeleteSafely();
        });
    }

    /**
     * @return array{id: int, name: string, code: string|null, color: string|null}
     */
    public function toFormData(GlobalServiceType $type): array
    {
        return [
            'id' => $type->id,
            'name' => $type->name,
            'code' => $type->code,
            'color' => $type->color,
        ];
    }

    /**
     * @return array{id: int, name: string, code: string|null, color: string|null, created_at: string|null}
     */
    public function toListItem(GlobalServiceType $type): array
    {
        return [
            'id' => $type->id,
            'name' => $type->name,
            'code' => $type->code,
            'color' => $type->color,
            'created_at' => $type->created_at?->toIso8601String(),
        ];
    }
}
