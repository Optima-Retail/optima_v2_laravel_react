<?php

declare(strict_types=1);

namespace App\Domain\Config\ExpenseTypes\Services;

use App\Models\ExpenseType;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class ExpenseTypeService
{
    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, ExpenseType>
     */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort($filters, ['id', 'name'], 'name');

        return ExpenseType::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%");
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
            ->through(fn (ExpenseType $type): array => $this->toListItem($type));
    }

    /**
     * @param  array{name: string}  $data
     */
    public function create(array $data): ExpenseType
    {
        return DB::transaction(function () use ($data): ExpenseType {
            return ExpenseType::query()->create([
                'name' => $data['name'],
            ]);
        });
    }

    /**
     * @param  array{name: string}  $data
     */
    public function update(ExpenseType $type, array $data): ExpenseType
    {
        return DB::transaction(function () use ($type, $data): ExpenseType {
            $type->update([
                'name' => $data['name'],
            ]);

            return $type->fresh() ?? $type;
        });
    }

    public function delete(ExpenseType $type): void
    {
        if ($type->trashed()) {
            return;
        }

        DB::transaction(function () use ($type): void {
            $type->delete();
        });
    }

    /**
     * @return array{id: int, name: string}
     */
    public function toFormData(ExpenseType $type): array
    {
        return [
            'id' => $type->id,
            'name' => $type->name,
        ];
    }

    /**
     * @return array{id: int, name: string, created_at: string|null}
     */
    public function toListItem(ExpenseType $type): array
    {
        return [
            'id' => $type->id,
            'name' => $type->name,
            'created_at' => $type->created_at?->toIso8601String(),
        ];
    }
}
