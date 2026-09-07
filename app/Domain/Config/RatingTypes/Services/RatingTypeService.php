<?php

declare(strict_types=1);

namespace App\Domain\Config\RatingTypes\Services;

use App\Models\RatingType;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class RatingTypeService
{
    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, RatingType>
     */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort($filters, ['id', 'name', 'code', 'max_score'], 'name');

        return RatingType::query()
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
            ->through(fn (RatingType $ratingType): array => $this->toListItem($ratingType));
    }

    /**
     * @param  array{name: string, code: string, max_score: int}  $data
     */
    public function create(array $data): RatingType
    {
        return DB::transaction(function () use ($data): RatingType {
            return RatingType::query()->create([
                'name' => $data['name'],
                'code' => strtolower($data['code']),
                'max_score' => $data['max_score'],
            ]);
        });
    }

    /**
     * @param  array{name: string, code: string, max_score: int}  $data
     */
    public function update(RatingType $ratingType, array $data): RatingType
    {
        return DB::transaction(function () use ($ratingType, $data): RatingType {
            $ratingType->update([
                'name' => $data['name'],
                'code' => strtolower($data['code']),
                'max_score' => $data['max_score'],
            ]);

            return $ratingType->fresh();
        });
    }

    public function delete(RatingType $ratingType): void
    {
        if ($ratingType->trashed()) {
            return;
        }

        DB::transaction(function () use ($ratingType): void {
            $ratingType->softDeleteSafely();
        });
    }

    /**
     * @return array{id: int, name: string, code: string, max_score: int}
     */
    public function toFormData(RatingType $ratingType): array
    {
        return [
            'id' => $ratingType->id,
            'name' => $ratingType->name,
            'code' => $ratingType->code,
            'max_score' => $ratingType->max_score,
        ];
    }

    /**
     * @return array{id: int, name: string, code: string, max_score: int, created_at: string|null}
     */
    public function toListItem(RatingType $ratingType): array
    {
        return [
            'id' => $ratingType->id,
            'name' => $ratingType->name,
            'code' => $ratingType->code,
            'max_score' => $ratingType->max_score,
            'created_at' => $ratingType->created_at?->toIso8601String(),
        ];
    }
}
