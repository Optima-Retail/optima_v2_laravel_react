<?php

declare(strict_types=1);

namespace App\Domain\Config\TasksToPerform\Services;

use App\Domain\Config\TasksToPerform\Enums\TaskDocumentType;
use App\Models\TaskToPerform;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class TaskToPerformService
{
    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, TaskToPerform>
     */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort(
            $filters,
            ['id', 'title', 'document_type', 'document_id', 'is_completed'],
            'id',
            'desc',
        );

        return TaskToPerform::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('document_type', 'like', "%{$search}%");
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
            ->through(fn (TaskToPerform $task): array => $this->toListItem($task));
    }

    /**
     * @param  array{
     *     title?: string|null,
     *     description?: string|null,
     *     is_completed: bool,
     *     document_type: string,
     *     document_id?: int|null
     * }  $data
     */
    public function create(array $data): TaskToPerform
    {
        return DB::transaction(function () use ($data): TaskToPerform {
            return TaskToPerform::query()->create([
                'title' => $data['title'] ?? null,
                'description' => $data['description'] ?? null,
                'is_completed' => (bool) ($data['is_completed'] ?? false),
                'document_type' => $data['document_type'],
                'document_id' => $data['document_id'] ?? null,
            ]);
        });
    }

    /**
     * @param  array{
     *     title?: string|null,
     *     description?: string|null,
     *     is_completed: bool,
     *     document_type: string,
     *     document_id?: int|null
     * }  $data
     */
    public function update(TaskToPerform $task, array $data): TaskToPerform
    {
        return DB::transaction(function () use ($task, $data): TaskToPerform {
            $task->update([
                'title' => $data['title'] ?? null,
                'description' => $data['description'] ?? null,
                'is_completed' => (bool) ($data['is_completed'] ?? false),
                'document_type' => $data['document_type'],
                'document_id' => $data['document_id'] ?? null,
            ]);

            return $task->fresh() ?? $task;
        });
    }

    public function delete(TaskToPerform $task): void
    {
        if ($task->trashed()) {
            return;
        }

        DB::transaction(function () use ($task): void {
            $task->delete();
        });
    }

    /**
     * @return array{
     *     id: int,
     *     title: string|null,
     *     description: string|null,
     *     is_completed: bool,
     *     document_type: string,
     *     document_id: int|null
     * }
     */
    public function toFormData(TaskToPerform $task): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'is_completed' => $task->is_completed,
            'document_type' => $task->document_type instanceof TaskDocumentType
                ? $task->document_type->value
                : (string) $task->document_type,
            'document_id' => $task->document_id,
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     title: string|null,
     *     description: string|null,
     *     is_completed: bool,
     *     document_type: string,
     *     document_id: int|null,
     *     created_at: string|null
     * }
     */
    public function toListItem(TaskToPerform $task): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'is_completed' => $task->is_completed,
            'document_type' => $task->document_type instanceof TaskDocumentType
                ? $task->document_type->value
                : (string) $task->document_type,
            'document_id' => $task->document_id,
            'created_at' => $task->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function documentTypeOptions(): array
    {
        return TaskDocumentType::options();
    }
}
