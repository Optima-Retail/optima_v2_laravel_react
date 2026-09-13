<?php

declare(strict_types=1);

namespace App\Domain\Config\ComplimentTypes\Services;

use App\Models\ComplimentType;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class ComplimentTypeService
{
    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, ComplimentType>
     */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort($filters, ['id', 'name'], 'name');

        return ComplimentType::query()
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
            ->through(fn (ComplimentType $type): array => $this->toListItem($type));
    }

    /**
     * @param  array{name: string}  $data
     */
    public function create(array $data): ComplimentType
    {
        return DB::transaction(function () use ($data): ComplimentType {
            return ComplimentType::query()->create([
                'name' => $data['name'],
            ]);
        });
    }

    /**
     * @param  array{name: string}  $data
     */
    public function update(ComplimentType $complimentType, array $data): ComplimentType
    {
        return DB::transaction(function () use ($complimentType, $data): ComplimentType {
            $complimentType->update([
                'name' => $data['name'],
            ]);

            return $complimentType->fresh() ?? $complimentType;
        });
    }

    public function delete(ComplimentType $complimentType): void
    {
        if ($complimentType->trashed()) {
            return;
        }

        DB::transaction(function () use ($complimentType): void {
            $complimentType->delete();
        });
    }

    /**
     * @return array{id: int, name: string}
     */
    public function toFormData(ComplimentType $complimentType): array
    {
        return [
            'id' => $complimentType->id,
            'name' => $complimentType->name,
        ];
    }

    /**
     * @return array{id: int, name: string}
     */
    public function toListItem(ComplimentType $complimentType): array
    {
        return [
            'id' => $complimentType->id,
            'name' => $complimentType->name,
        ];
    }
}
