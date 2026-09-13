<?php

declare(strict_types=1);

namespace App\Domain\Config\TechnicianIncidentTypes\Services;

use App\Models\TechnicianIncidentType;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class TechnicianIncidentTypeService
{
    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, TechnicianIncidentType>
     */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort($filters, ['id', 'name', 'due_days'], 'name');

        return TechnicianIncidentType::query()
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
            ->through(fn (TechnicianIncidentType $type): array => $this->toListItem($type));
    }

    /**
     * @param  array{name: string, due_days: int, send_mail_to_technician: bool}  $data
     */
    public function create(array $data): TechnicianIncidentType
    {
        return DB::transaction(function () use ($data): TechnicianIncidentType {
            return TechnicianIncidentType::query()->create([
                'name' => $data['name'],
                'due_days' => (int) $data['due_days'],
                'send_mail_to_technician' => (bool) $data['send_mail_to_technician'],
            ]);
        });
    }

    /**
     * @param  array{name: string, due_days: int, send_mail_to_technician: bool}  $data
     */
    public function update(TechnicianIncidentType $type, array $data): TechnicianIncidentType
    {
        return DB::transaction(function () use ($type, $data): TechnicianIncidentType {
            $type->update([
                'name' => $data['name'],
                'due_days' => (int) $data['due_days'],
                'send_mail_to_technician' => (bool) $data['send_mail_to_technician'],
            ]);

            return $type->fresh() ?? $type;
        });
    }

    public function delete(TechnicianIncidentType $type): void
    {
        if ($type->trashed()) {
            return;
        }

        DB::transaction(function () use ($type): void {
            $type->delete();
        });
    }

    /**
     * @return array{id: int, name: string, due_days: int, send_mail_to_technician: bool}
     */
    public function toFormData(TechnicianIncidentType $type): array
    {
        return [
            'id' => $type->id,
            'name' => $type->name,
            'due_days' => $type->due_days,
            'send_mail_to_technician' => $type->send_mail_to_technician,
        ];
    }

    /**
     * @return array{id: int, name: string, due_days: int, send_mail_to_technician: bool, created_at: string|null}
     */
    public function toListItem(TechnicianIncidentType $type): array
    {
        return [
            'id' => $type->id,
            'name' => $type->name,
            'due_days' => $type->due_days,
            'send_mail_to_technician' => $type->send_mail_to_technician,
            'created_at' => $type->created_at?->toIso8601String(),
        ];
    }
}
