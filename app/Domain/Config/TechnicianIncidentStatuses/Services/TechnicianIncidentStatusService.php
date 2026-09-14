<?php

declare(strict_types=1);

namespace App\Domain\Config\TechnicianIncidentStatuses\Services;

use App\Models\TechnicianIncidentStatus;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class TechnicianIncidentStatusService
{
    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, TechnicianIncidentStatus>
     */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort(
            $filters,
            ['id', 'name', 'lifecycle', 'is_open', 'is_default', 'marks_verified', 'sets_response_date'],
            'lifecycle',
        );

        return TechnicianIncidentStatus::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy($sort, $direction)
            ->orderBy('id')
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
            ->through(fn (TechnicianIncidentStatus $status): array => $this->toListItem($status));
    }

    /**
     * @param  array{
     *     name: string,
     *     color?: string|null,
     *     lifecycle?: int|null,
     *     is_open: bool,
     *     is_default?: bool,
     *     marks_verified?: bool,
     *     sets_response_date?: bool
     * }  $data
     */
    public function create(array $data): TechnicianIncidentStatus
    {
        return DB::transaction(function () use ($data): TechnicianIncidentStatus {
            $payload = $this->attributes($data);

            if ($payload['is_default']) {
                $this->clearFlag('is_default');
            }

            if ($payload['marks_verified']) {
                $this->clearFlag('marks_verified');
            }

            return TechnicianIncidentStatus::query()->create($payload);
        });
    }

    /**
     * @param  array{
     *     name: string,
     *     color?: string|null,
     *     lifecycle?: int|null,
     *     is_open: bool,
     *     is_default?: bool,
     *     marks_verified?: bool,
     *     sets_response_date?: bool
     * }  $data
     */
    public function update(TechnicianIncidentStatus $status, array $data): TechnicianIncidentStatus
    {
        return DB::transaction(function () use ($status, $data): TechnicianIncidentStatus {
            $payload = $this->attributes($data);

            if ($payload['is_default']) {
                $this->clearFlag('is_default', $status->id);
            }

            if ($payload['marks_verified']) {
                $this->clearFlag('marks_verified', $status->id);
            }

            $status->update($payload);

            return $status->fresh() ?? $status;
        });
    }

    public function delete(TechnicianIncidentStatus $status): void
    {
        if ($status->trashed()) {
            return;
        }

        DB::transaction(function () use ($status): void {
            $status->delete();
        });
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     color: string|null,
     *     lifecycle: int|null,
     *     is_open: bool,
     *     is_default: bool,
     *     marks_verified: bool,
     *     sets_response_date: bool
     * }
     */
    public function toFormData(TechnicianIncidentStatus $status): array
    {
        return [
            'id' => $status->id,
            'name' => $status->name,
            'color' => $status->color,
            'lifecycle' => $status->lifecycle,
            'is_open' => $status->is_open,
            'is_default' => $status->is_default,
            'marks_verified' => $status->marks_verified,
            'sets_response_date' => $status->sets_response_date,
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     color: string|null,
     *     lifecycle: int|null,
     *     is_open: bool,
     *     is_default: bool,
     *     marks_verified: bool,
     *     sets_response_date: bool,
     *     created_at: string|null
     * }
     */
    public function toListItem(TechnicianIncidentStatus $status): array
    {
        return [
            'id' => $status->id,
            'name' => $status->name,
            'color' => $status->color,
            'lifecycle' => $status->lifecycle,
            'is_open' => $status->is_open,
            'is_default' => $status->is_default,
            'marks_verified' => $status->marks_verified,
            'sets_response_date' => $status->sets_response_date,
            'created_at' => $status->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     name: string,
     *     color: string|null,
     *     lifecycle: int|null,
     *     is_open: bool,
     *     is_default: bool,
     *     marks_verified: bool,
     *     sets_response_date: bool
     * }
     */
    private function attributes(array $data): array
    {
        return [
            'name' => (string) $data['name'],
            'color' => ($data['color'] ?? null) ?: null,
            'lifecycle' => $data['lifecycle'] ?? null,
            'is_open' => (bool) $data['is_open'],
            'is_default' => (bool) ($data['is_default'] ?? false),
            'marks_verified' => (bool) ($data['marks_verified'] ?? false),
            'sets_response_date' => (bool) ($data['sets_response_date'] ?? false),
        ];
    }

    private function clearFlag(string $column, ?int $exceptId = null): void
    {
        TechnicianIncidentStatus::query()
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->where($column, true)
            ->update([$column => false]);
    }
}
