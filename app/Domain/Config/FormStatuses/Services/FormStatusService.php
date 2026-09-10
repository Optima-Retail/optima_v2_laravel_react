<?php

declare(strict_types=1);

namespace App\Domain\Config\FormStatuses\Services;

use App\Models\FormStatus;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class FormStatusService
{
    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, FormStatus>
     */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort($filters, ['id', 'name', 'next_status_id', 'is_active'], 'id');

        return FormStatus::query()
            ->with('nextStatus:id,name')
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
            ->through(fn (FormStatus $status): array => $this->toListItem($status));
    }

    /**
     * @param  array{name: string, next_status_id?: int|null, is_active: bool}  $data
     */
    public function create(array $data): FormStatus
    {
        return DB::transaction(function () use ($data): FormStatus {
            return FormStatus::query()->create([
                'name' => $data['name'],
                'next_status_id' => $data['next_status_id'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);
        });
    }

    /**
     * @param  array{name: string, next_status_id?: int|null, is_active: bool}  $data
     */
    public function update(FormStatus $status, array $data): FormStatus
    {
        return DB::transaction(function () use ($status, $data): FormStatus {
            $status->update([
                'name' => $data['name'],
                'next_status_id' => $data['next_status_id'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            return $status->fresh(['nextStatus:id,name']);
        });
    }

    public function delete(FormStatus $status): void
    {
        if ($status->trashed()) {
            return;
        }

        DB::transaction(function () use ($status): void {
            $status->delete();
        });
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function statusOptions(?int $excludeId = null, ?int $includeId = null): array
    {
        /** @var Collection<int, FormStatus> $statuses */
        $statuses = FormStatus::query()
            ->where(function ($query) use ($includeId): void {
                $query->where('is_active', true);

                if ($includeId !== null) {
                    $query->orWhereKey($includeId);
                }
            })
            ->orderBy('id')
            ->get(['id', 'name']);

        return $statuses
            ->when($excludeId !== null, fn (Collection $items) => $items->reject(
                fn (FormStatus $status): bool => $status->id === $excludeId,
            ))
            ->values()
            ->map(fn (FormStatus $status): array => [
                'id' => $status->id,
                'label' => $status->name,
            ])
            ->all();
    }

    /**
     * @return array{id: int, name: string, next_status_id: int|null, is_active: bool}
     */
    public function toFormData(FormStatus $status): array
    {
        return [
            'id' => $status->id,
            'name' => $status->name,
            'next_status_id' => $status->next_status_id,
            'is_active' => $status->is_active,
        ];
    }

    /**
     * @return array{id: int, name: string, next_status_id: int|null, next_status_name: string|null, is_active: bool, created_at: string|null}
     */
    public function toListItem(FormStatus $status): array
    {
        return [
            'id' => $status->id,
            'name' => $status->name,
            'next_status_id' => $status->next_status_id,
            'next_status_name' => $status->nextStatus?->name,
            'is_active' => $status->is_active,
            'created_at' => $status->created_at?->toIso8601String(),
        ];
    }
}
