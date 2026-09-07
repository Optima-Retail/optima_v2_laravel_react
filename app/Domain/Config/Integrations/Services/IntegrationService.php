<?php

declare(strict_types=1);

namespace App\Domain\Config\Integrations\Services;

use App\Models\Integration;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class IntegrationService
{
    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, Integration>
     */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort($filters, ['id', 'name', 'code'], 'name');

        return Integration::query()
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
            ->through(fn (Integration $integration): array => $this->toListItem($integration));
    }

    /**
     * @param  array{name: string, code: string}  $data
     */
    public function create(array $data): Integration
    {
        return DB::transaction(function () use ($data): Integration {
            return Integration::query()->create([
                'name' => $data['name'],
                'code' => strtolower($data['code']),
            ]);
        });
    }

    /**
     * @param  array{name: string, code: string}  $data
     */
    public function update(Integration $integration, array $data): Integration
    {
        return DB::transaction(function () use ($integration, $data): Integration {
            $integration->update([
                'name' => $data['name'],
                'code' => strtolower($data['code']),
            ]);

            return $integration->fresh();
        });
    }

    public function delete(Integration $integration): void
    {
        if ($integration->trashed()) {
            return;
        }

        DB::transaction(function () use ($integration): void {
            $integration->softDeleteSafely();
        });
    }

    /**
     * @return array{id: int, name: string, code: string}
     */
    public function toFormData(Integration $integration): array
    {
        return [
            'id' => $integration->id,
            'name' => $integration->name,
            'code' => $integration->code,
        ];
    }

    /**
     * @return array{id: int, name: string, code: string, created_at: string|null}
     */
    public function toListItem(Integration $integration): array
    {
        return [
            'id' => $integration->id,
            'name' => $integration->name,
            'code' => $integration->code,
            'created_at' => $integration->created_at?->toIso8601String(),
        ];
    }
}
