<?php

declare(strict_types=1);

namespace App\Domain\Config\Checklists\Services;

use App\Domain\Config\Checklists\Enums\ChecklistDocumentType;
use App\Domain\WorkOrders\Enums\WorkOrderStage;
use App\Models\Checklist;
use App\Models\WorkOrderStatus;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ChecklistService
{
    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, Checklist>
     */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort(
            $filters,
            ['id', 'label', 'document_type', 'sort_order', 'requires_validation'],
            'sort_order',
        );

        return Checklist::query()
            ->with(['workOrderStatus:id,name,color,kind'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner
                        ->where('label', 'like', "%{$search}%")
                        ->orWhere('document_type', 'like', "%{$search}%");
                });
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
            ->through(fn (Checklist $checklist): array => $this->toListItem($checklist));
    }

    /**
     * @param  array{
     *     label: string,
     *     requires_validation: bool,
     *     document_type: string,
     *     work_order_status_id: int,
     *     sort_order?: int
     * }  $data
     */
    public function create(array $data): Checklist
    {
        return DB::transaction(function () use ($data): Checklist {
            return Checklist::query()->create([
                'label' => $data['label'],
                'requires_validation' => (bool) ($data['requires_validation'] ?? true),
                'document_type' => $data['document_type'],
                'work_order_status_id' => (int) $data['work_order_status_id'],
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ]);
        });
    }

    /**
     * @param  array{
     *     label: string,
     *     requires_validation: bool,
     *     document_type: string,
     *     work_order_status_id: int,
     *     sort_order?: int
     * }  $data
     */
    public function update(Checklist $checklist, array $data): Checklist
    {
        return DB::transaction(function () use ($checklist, $data): Checklist {
            $checklist->update([
                'label' => $data['label'],
                'requires_validation' => (bool) ($data['requires_validation'] ?? true),
                'document_type' => $data['document_type'],
                'work_order_status_id' => (int) $data['work_order_status_id'],
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ]);

            return $checklist->fresh(['workOrderStatus:id,name,color,kind']) ?? $checklist;
        });
    }

    public function delete(Checklist $checklist): void
    {
        if ($checklist->trashed()) {
            return;
        }

        DB::transaction(function () use ($checklist): void {
            $checklist->delete();
        });
    }

    /**
     * @return array{
     *     id: int,
     *     label: string,
     *     requires_validation: bool,
     *     document_type: string,
     *     work_order_status_id: int|null,
     *     sort_order: int
     * }
     */
    public function toFormData(Checklist $checklist): array
    {
        return [
            'id' => $checklist->id,
            'label' => $checklist->label,
            'requires_validation' => $checklist->requires_validation,
            'document_type' => $checklist->document_type instanceof ChecklistDocumentType
                ? $checklist->document_type->value
                : (string) $checklist->document_type,
            'work_order_status_id' => $checklist->work_order_status_id,
            'sort_order' => $checklist->sort_order,
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     label: string,
     *     requires_validation: bool,
     *     document_type: string,
     *     status_label: string,
     *     status_color: string|null,
     *     sort_order: int,
     *     created_at: string|null
     * }
     */
    public function toListItem(Checklist $checklist): array
    {
        $status = $checklist->workOrderStatus;

        return [
            'id' => $checklist->id,
            'label' => $checklist->label,
            'requires_validation' => $checklist->requires_validation,
            'document_type' => $checklist->document_type instanceof ChecklistDocumentType
                ? $checklist->document_type->value
                : (string) $checklist->document_type,
            'status_label' => $status?->name ?? '',
            'status_color' => $status?->color,
            'sort_order' => $checklist->sort_order,
            'created_at' => $checklist->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function documentTypeOptions(): array
    {
        return ChecklistDocumentType::options();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function statusOptions(WorkOrderStage $kind): array
    {
        /** @var Collection<int, WorkOrderStatus> $statuses */
        $statuses = WorkOrderStatus::query()
            ->kind($kind)
            ->orderBy('lifecycle')
            ->orderBy('id')
            ->get(['id', 'name']);

        return $statuses
            ->map(fn (WorkOrderStatus $status): array => [
                'id' => $status->id,
                'label' => $status->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function workOrderStatusOptions(): array
    {
        return $this->statusOptions(WorkOrderStage::WorkOrder);
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function estimateStatusOptions(): array
    {
        return $this->statusOptions(WorkOrderStage::Estimate);
    }
}
