<?php

declare(strict_types=1);

namespace App\Domain\Config\Series\Services;

use App\Models\Series;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class SeriesService
{
    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, Series>
     */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort($filters, ['id', 'key', 'color', 'is_selectable', 'credit_note_series_id'], 'key');

        return Series::query()
            ->with('creditNoteSeries')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner
                        ->where('key', 'like', "%{$search}%")
                        ->orWhere('color', 'like', "%{$search}%");
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
            ->through(fn (Series $series): array => $this->toListItem($series));
    }

    /**
     * @param  array{key: string, color: string, is_selectable: bool, credit_note_series_id?: int|null}  $data
     */
    public function create(array $data): Series
    {
        return DB::transaction(function () use ($data): Series {
            return Series::query()->create([
                'key' => strtoupper($data['key']),
                'color' => $data['color'],
                'is_selectable' => (bool) $data['is_selectable'],
                'credit_note_series_id' => $data['credit_note_series_id'] ?? null,
            ]);
        });
    }

    /**
     * @param  array{key: string, color: string, is_selectable: bool, credit_note_series_id?: int|null}  $data
     */
    public function update(Series $series, array $data): Series
    {
        return DB::transaction(function () use ($series, $data): Series {
            $series->update([
                'key' => strtoupper($data['key']),
                'color' => $data['color'],
                'is_selectable' => (bool) $data['is_selectable'],
                'credit_note_series_id' => $data['credit_note_series_id'] ?? null,
            ]);

            return $series->fresh(['creditNoteSeries']);
        });
    }

    public function delete(Series $series): void
    {
        if ($series->trashed()) {
            return;
        }

        DB::transaction(function () use ($series): void {
            Series::query()
                ->where('credit_note_series_id', $series->id)
                ->where('id', '!=', $series->id)
                ->update(['credit_note_series_id' => null]);

            $series->softDeleteSafely();
        });
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function seriesOptions(): array
    {
        /** @var Collection<int, Series> $rows */
        $rows = Series::query()->orderBy('key')->get(['id', 'key']);

        return $rows
            ->map(fn (Series $series): array => [
                'id' => $series->id,
                'label' => $series->key,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, key: string, color: string, is_selectable: bool, credit_note_series_id: int|null}
     */
    public function toFormData(Series $series): array
    {
        return [
            'id' => $series->id,
            'key' => $series->key,
            'color' => $series->color,
            'is_selectable' => $series->is_selectable,
            'credit_note_series_id' => $series->credit_note_series_id,
        ];
    }

    /**
     * @return array{id: int, key: string, color: string, is_selectable: bool, credit_note_series_id: int|null, credit_note_series_key: string|null, created_at: string|null}
     */
    public function toListItem(Series $series): array
    {
        return [
            'id' => $series->id,
            'key' => $series->key,
            'color' => $series->color,
            'is_selectable' => $series->is_selectable,
            'credit_note_series_id' => $series->credit_note_series_id,
            'credit_note_series_key' => $series->creditNoteSeries?->key,
            'created_at' => $series->created_at?->toIso8601String(),
        ];
    }
}
