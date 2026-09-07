<?php

declare(strict_types=1);

namespace App\Domain\Config\Currencies\Services;

use App\Models\Currency;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class CurrencyService
{
    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null, page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, Currency>
     */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort($filters, ['id', 'name', 'code'], 'name');
        $page = max(1, (int) ($filters['page'] ?? request()->integer('page', 1)));

        return Currency::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->orderBy($sort, $direction)
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();
    }

    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null, page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateForWeb(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        return $this->paginate($filters, $perPage)
            ->through(fn (Currency $currency): array => $this->toListItem($currency));
    }

    /**
     * @param  array{name: string, code: string}  $data
     */
    public function create(array $data): Currency
    {
        return DB::transaction(function () use ($data): Currency {
            return Currency::query()->create([
                'name' => $data['name'],
                'code' => strtoupper($data['code']),
            ]);
        });
    }

    /**
     * @param  array{name: string, code: string}  $data
     */
    public function update(Currency $currency, array $data): Currency
    {
        return DB::transaction(function () use ($currency, $data): Currency {
            $currency->update([
                'name' => $data['name'],
                'code' => strtoupper($data['code']),
            ]);

            return $currency->fresh();
        });
    }

    public function delete(Currency $currency): void
    {
        if ($currency->trashed()) {
            return;
        }

        DB::transaction(function () use ($currency): void {
            $currency->softDeleteSafely();
        });
    }

    /**
     * @return array{id: int, name: string, code: string}
     */
    public function toFormData(Currency $currency): array
    {
        return [
            'id' => $currency->id,
            'name' => $currency->name,
            'code' => $currency->code,
        ];
    }

    /**
     * @return array{id: int, name: string, code: string, created_at: string|null}
     */
    public function toListItem(Currency $currency): array
    {
        return [
            'id' => $currency->id,
            'name' => $currency->name,
            'code' => $currency->code,
            'created_at' => $currency->created_at?->toIso8601String(),
        ];
    }
}
