<?php

declare(strict_types=1);

namespace App\Domain\SavedFilters\Services;

use App\Models\SavedFilter;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class SavedFilterService
{
    /**
     * @return Collection<int, array{id: int, name: string, page_key: string, filters: array<string, mixed>, is_default: bool}>
     */
    public function listForUser(User $user, string $pageKey): Collection
    {
        return SavedFilter::query()
            ->where('user_id', $user->id)
            ->where('page_key', $pageKey)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (SavedFilter $filter): array => $this->toPayload($filter));
    }

    /**
     * @param  array{name: string, page_key: string, filters: array<string, mixed>, is_default?: bool}  $data
     * @return array{id: int, name: string, page_key: string, filters: array<string, mixed>, is_default: bool}
     */
    public function upsert(User $user, array $data): array
    {
        return DB::transaction(function () use ($user, $data): array {
            $filter = SavedFilter::withTrashed()
                ->where('user_id', $user->id)
                ->where('page_key', $data['page_key'])
                ->where('name', $data['name'])
                ->first();

            if ($filter === null) {
                $filter = new SavedFilter([
                    'user_id' => $user->id,
                    'page_key' => $data['page_key'],
                    'name' => $data['name'],
                ]);
            } elseif ($filter->trashed()) {
                $filter->restore();
            }

            $filter->filters = $this->normalizeFilters($data['filters']);
            $makeDefault = (bool) ($data['is_default'] ?? false);

            if (! $filter->exists) {
                $filter->is_default = $makeDefault;
            } elseif ($makeDefault) {
                $filter->is_default = true;
            }

            $filter->save();

            if ($filter->is_default) {
                $this->clearOtherDefaults($user->id, $data['page_key'], $filter->id);
            }

            return $this->toPayload($filter->fresh() ?? $filter);
        });
    }

    public function delete(User $user, SavedFilter $filter): void
    {
        abort_unless((int) $filter->user_id === (int) $user->id, 403);

        if ($filter->trashed()) {
            return;
        }

        $filter->delete();
    }

    /**
     * @return array{id: int, name: string, page_key: string, filters: array<string, mixed>, is_default: bool}
     */
    public function setDefault(User $user, SavedFilter $filter, bool $isDefault): array
    {
        abort_unless((int) $filter->user_id === (int) $user->id, 403);

        return DB::transaction(function () use ($user, $filter, $isDefault): array {
            if ($isDefault) {
                $this->clearOtherDefaults($user->id, $filter->page_key, $filter->id);
            }

            $filter->update(['is_default' => $isDefault]);

            return $this->toPayload($filter->fresh() ?? $filter);
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    private function normalizeFilters(array $filters): array
    {
        $normalized = [];

        foreach ($filters as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            if (is_array($value)) {
                continue;
            }

            $string = trim((string) $value);

            if ($string === '') {
                continue;
            }

            $normalized[$key] = $string;
        }

        return $normalized;
    }

    private function clearOtherDefaults(int $userId, string $pageKey, int $exceptId): void
    {
        SavedFilter::query()
            ->where('user_id', $userId)
            ->where('page_key', $pageKey)
            ->where('id', '!=', $exceptId)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }

    /**
     * @return array{id: int, name: string, page_key: string, filters: array<string, mixed>, is_default: bool}
     */
    private function toPayload(SavedFilter $filter): array
    {
        return [
            'id' => $filter->id,
            'name' => $filter->name,
            'page_key' => $filter->page_key,
            'filters' => is_array($filter->filters) ? $filter->filters : [],
            'is_default' => (bool) $filter->is_default,
        ];
    }
}
