<?php

declare(strict_types=1);

namespace App\Domain\Config\IncidentTypes\Services;

use App\Models\IncidentType;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class IncidentTypeService
{
    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, IncidentType>
     */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort($filters, ['id', 'name'], 'id');

        return IncidentType::query()
            ->with('defaultPriority')
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
            ->through(fn (IncidentType $type): array => $this->toListItem($type));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): IncidentType
    {
        return DB::transaction(function () use ($data): IncidentType {
            return IncidentType::query()->create($this->attributes($data));
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(IncidentType $type, array $data): IncidentType
    {
        return DB::transaction(function () use ($type, $data): IncidentType {
            $type->update($this->attributes($data));

            return $type->fresh(['defaultPriority']) ?? $type;
        });
    }

    /**
     * Soft-delete a type. Records are never hard-deleted.
     */
    public function delete(IncidentType $type): void
    {
        if ($type->trashed()) {
            return;
        }

        DB::transaction(function () use ($type): void {
            $type->delete();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function toFormData(IncidentType $type): array
    {
        $config = $type->formConfig();

        return [
            'id' => $type->id,
            'name' => $type->name,
            'color' => $type->color,
            'default_priority_id' => $type->default_priority_id,
            'origin_selectable' => $config['origin_selectable'],
            'origin_options' => $config['origin_options'],
            'default_origin_type' => $config['default_origin_type'],
            'origin_required' => $config['origin_required'],
            'related_type' => $config['related_type'],
            'show_related' => $config['show_related'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toListItem(IncidentType $type): array
    {
        return [
            'id' => $type->id,
            'name' => $type->name,
            'color' => $type->color,
            'default_priority_id' => $type->default_priority_id,
            'default_priority_name' => $type->defaultPriority?->name,
            'default_priority_color' => $type->defaultPriority?->color,
            'origin_required' => (bool) $type->origin_required,
            'created_at' => $type->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        /** @var list<string> $options */
        $options = array_values(array_filter(
            is_array($data['origin_options'] ?? null) ? $data['origin_options'] : [],
            fn ($value): bool => is_string($value) && in_array($value, ['company', 'brand', 'establishment'], true),
        ));

        $defaultOrigin = filled($data['default_origin_type'] ?? null)
            ? (string) $data['default_origin_type']
            : null;

        if ($defaultOrigin !== null && $options !== [] && ! in_array($defaultOrigin, $options, true)) {
            $defaultOrigin = null;
        }

        return [
            'name' => $data['name'],
            'color' => ($data['color'] ?? null) ?: null,
            'default_priority_id' => $data['default_priority_id'] ?? null,
            'origin_selectable' => (bool) ($data['origin_selectable'] ?? false),
            'origin_options' => $options,
            'default_origin_type' => $defaultOrigin,
            'origin_required' => (bool) ($data['origin_required'] ?? true),
            'related_type' => filled($data['related_type'] ?? null) ? (string) $data['related_type'] : null,
            'show_related' => (bool) ($data['show_related'] ?? false),
        ];
    }
}
