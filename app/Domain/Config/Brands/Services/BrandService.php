<?php

declare(strict_types=1);

namespace App\Domain\Config\Brands\Services;

use App\Models\Brand;
use App\Models\User;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class BrandService
{
    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, Brand>
     */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort(
            $filters,
            ['id', 'name', 'account_manager_id', 'commercial_manager_id', 'loyalty_meeting_frequency', 'created_at'],
            'name',
        );

        return Brand::query()
            ->with(['accountManager', 'commercialManager', 'collaborators'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('loyalty_meeting_frequency', 'like', "%{$search}%")
                        ->orWhereHas('accountManager', fn ($users) => $users->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('commercialManager', fn ($users) => $users->where('name', 'like', "%{$search}%"));
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
            ->through(fn (Brand $brand): array => $this->toListItem($brand));
    }

    /**
     * @param  array{
     *     name: string,
     *     account_manager_id?: int|null,
     *     commercial_manager_id?: int|null,
     *     collaborator_ids?: list<int>,
     *     loyalty_meeting_frequency?: string|null,
     *     is_quality_control_contactable: bool,
     *     send_debt_reminders: bool
     * }  $data
     */
    public function create(array $data): Brand
    {
        return DB::transaction(function () use ($data): Brand {
            $brand = Brand::query()->create(Arr::except($data, ['collaborator_ids']));
            $brand->collaborators()->sync($data['collaborator_ids'] ?? []);

            return $brand->load(['accountManager', 'commercialManager', 'collaborators']);
        });
    }

    /**
     * @param  array{
     *     name: string,
     *     account_manager_id?: int|null,
     *     commercial_manager_id?: int|null,
     *     collaborator_ids?: list<int>,
     *     loyalty_meeting_frequency?: string|null,
     *     is_quality_control_contactable: bool,
     *     send_debt_reminders: bool
     * }  $data
     */
    public function update(Brand $brand, array $data): Brand
    {
        return DB::transaction(function () use ($brand, $data): Brand {
            $brand->update(Arr::except($data, ['collaborator_ids']));
            $brand->collaborators()->sync($data['collaborator_ids'] ?? []);

            return $brand->fresh(['accountManager', 'commercialManager', 'collaborators']);
        });
    }

    public function delete(Brand $brand): void
    {
        if ($brand->trashed()) {
            return;
        }

        DB::transaction(function () use ($brand): void {
            User::query()->where('brand_id', $brand->id)->update(['brand_id' => null]);
            $brand->collaborators()->detach();
            $brand->messages()->delete();
            $brand->softDeleteSafely();
        });
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function userOptions(): array
    {
        return User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'label' => "{$user->name} ({$user->email})",
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function toFormData(Brand $brand): array
    {
        $brand->loadMissing('collaborators');

        return [
            'id' => $brand->id,
            'name' => $brand->name,
            'account_manager_id' => $brand->account_manager_id,
            'commercial_manager_id' => $brand->commercial_manager_id,
            'collaborator_ids' => $brand->collaborators->pluck('id')->values()->all(),
            'loyalty_meeting_frequency' => $brand->loyalty_meeting_frequency,
            'is_quality_control_contactable' => $brand->is_quality_control_contactable,
            'send_debt_reminders' => $brand->send_debt_reminders,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toListItem(Brand $brand): array
    {
        return [
            ...$this->toFormData($brand),
            'account_manager_name' => $brand->accountManager?->name,
            'commercial_manager_name' => $brand->commercialManager?->name,
            'created_at' => $brand->created_at?->toIso8601String(),
        ];
    }
}
