<?php

declare(strict_types=1);

namespace App\Domain\Config\PaymentMethods\Services;

use App\Models\PaymentMethod;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class PaymentMethodService
{
    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, PaymentMethod>
     */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort($filters, ['id', 'name', 'days', 'code', 'is_active'], 'name');

        return PaymentMethod::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
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
            ->through(fn (PaymentMethod $method): array => $this->toListItem($method));
    }

    /**
     * @param  array{name: string, due_count?: int|null, days?: int|null, code?: string|null, is_active: bool}  $data
     */
    public function create(array $data): PaymentMethod
    {
        return DB::transaction(function () use ($data): PaymentMethod {
            return PaymentMethod::query()->create([
                'name' => $data['name'],
                'due_count' => $data['due_count'] ?? null,
                'days' => $data['days'] ?? null,
                'code' => $data['code'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);
        });
    }

    /**
     * @param  array{name: string, due_count?: int|null, days?: int|null, code?: string|null, is_active: bool}  $data
     */
    public function update(PaymentMethod $method, array $data): PaymentMethod
    {
        return DB::transaction(function () use ($method, $data): PaymentMethod {
            $method->update([
                'name' => $data['name'],
                'due_count' => $data['due_count'] ?? null,
                'days' => $data['days'] ?? null,
                'code' => $data['code'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            return $method->fresh() ?? $method;
        });
    }

    public function delete(PaymentMethod $method): void
    {
        if ($method->trashed()) {
            return;
        }

        DB::transaction(function () use ($method): void {
            $method->delete();
        });
    }

    /**
     * @return array{id: int, name: string, due_count: int|null, days: int|null, code: string|null, is_active: bool}
     */
    public function toFormData(PaymentMethod $method): array
    {
        return [
            'id' => $method->id,
            'name' => $method->name,
            'due_count' => $method->due_count,
            'days' => $method->days,
            'code' => $method->code,
            'is_active' => $method->is_active,
        ];
    }

    /**
     * @return array{id: int, name: string, due_count: int|null, days: int|null, code: string|null, is_active: bool, created_at: string|null}
     */
    public function toListItem(PaymentMethod $method): array
    {
        return [
            'id' => $method->id,
            'name' => $method->name,
            'due_count' => $method->due_count,
            'days' => $method->days,
            'code' => $method->code,
            'is_active' => $method->is_active,
            'created_at' => $method->created_at?->toIso8601String(),
        ];
    }
}
