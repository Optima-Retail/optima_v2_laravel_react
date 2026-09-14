<?php

declare(strict_types=1);

namespace App\Domain\Config\WorkOrderStatuses\Services;

use App\Domain\WorkOrders\Enums\WorkOrderStage;
use App\Models\WorkOrderStatus;
use App\Models\WorkOrderStatusTransition;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class WorkOrderStatusService
{
    /**
     * @param  array{search?: string|null, kind?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, WorkOrderStatus>
     */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $kind = trim((string) ($filters['kind'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort(
            $filters,
            ['id', 'name', 'kind', 'lifecycle', 'is_open', 'is_default', 'confirms_estimate', 'rejects_to_estimate', 'is_post_confirm_default', 'sets_sent_at'],
            'lifecycle',
        );

        return WorkOrderStatus::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%");
            })
            ->when($kind !== '' && in_array($kind, WorkOrderStage::values(), true), function ($query) use ($kind): void {
                $query->where('kind', $kind);
            })
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array{search?: string|null, kind?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateForWeb(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        return $this->paginate($filters, $perPage)
            ->through(fn (WorkOrderStatus $status): array => $this->toListItem($status));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): WorkOrderStatus
    {
        return DB::transaction(function () use ($data): WorkOrderStatus {
            $payload = $this->attributes($data);

            $this->clearExclusiveFlags($payload['kind'], $payload, null);

            $status = WorkOrderStatus::query()->create($payload);
            $this->syncTransitions($status, $data['transitions'] ?? []);

            return $status->fresh(['outgoingTransitions']) ?? $status;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(WorkOrderStatus $status, array $data): WorkOrderStatus
    {
        return DB::transaction(function () use ($status, $data): WorkOrderStatus {
            $payload = $this->attributes($data);

            $this->clearExclusiveFlags($payload['kind'], $payload, $status->id);

            $status->update($payload);
            $this->syncTransitions($status, $data['transitions'] ?? []);

            return $status->fresh(['outgoingTransitions']) ?? $status;
        });
    }

    public function delete(WorkOrderStatus $status): void
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
    public function targetOptions(WorkOrderStatus $status): array
    {
        $kind = $status->kind instanceof WorkOrderStage ? $status->kind : WorkOrderStage::from((string) $status->kind);

        return WorkOrderStatus::query()
            ->kind($kind)
            ->whereKeyNot($status->id)
            ->orderBy('lifecycle')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn (WorkOrderStatus $row): array => [
                'id' => (int) $row->id,
                'label' => $row->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function toFormData(WorkOrderStatus $status): array
    {
        $status->loadMissing('outgoingTransitions');

        return [
            'id' => $status->id,
            'name' => $status->name,
            'kind' => $status->kind instanceof WorkOrderStage ? $status->kind->value : (string) $status->kind,
            'color' => $status->color,
            'lifecycle' => $status->lifecycle,
            'is_open' => $status->is_open,
            'is_default' => $status->is_default,
            'confirms_estimate' => $status->confirms_estimate,
            'rejects_to_estimate' => $status->rejects_to_estimate,
            'is_post_confirm_default' => $status->is_post_confirm_default,
            'sets_sent_at' => $status->sets_sent_at,
            'transitions' => $status->outgoingTransitions
                ->map(fn (WorkOrderStatusTransition $row): array => [
                    'to_status_id' => (int) $row->to_status_id,
                    'requires_confirmation' => (bool) $row->requires_confirmation,
                    'requires_justification' => (bool) $row->requires_justification,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toListItem(WorkOrderStatus $status): array
    {
        return [
            'id' => $status->id,
            'name' => $status->name,
            'kind' => $status->kind instanceof WorkOrderStage ? $status->kind->value : (string) $status->kind,
            'color' => $status->color,
            'lifecycle' => $status->lifecycle,
            'is_open' => $status->is_open,
            'is_default' => $status->is_default,
            'confirms_estimate' => $status->confirms_estimate,
            'rejects_to_estimate' => $status->rejects_to_estimate,
            'is_post_confirm_default' => $status->is_post_confirm_default,
            'sets_sent_at' => $status->sets_sent_at,
            'created_at' => $status->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     name: string,
     *     kind: string,
     *     color: string|null,
     *     lifecycle: int|null,
     *     is_open: bool,
     *     is_default: bool,
     *     confirms_estimate: bool,
     *     rejects_to_estimate: bool,
     *     is_post_confirm_default: bool,
     *     sets_sent_at: bool
     * }
     */
    private function attributes(array $data): array
    {
        $kind = (string) $data['kind'];
        $isEstimate = $kind === WorkOrderStage::Estimate->value;

        return [
            'name' => (string) $data['name'],
            'kind' => $kind,
            'color' => ($data['color'] ?? null) ?: null,
            'lifecycle' => $data['lifecycle'] ?? null,
            'is_open' => (bool) $data['is_open'],
            'is_default' => (bool) ($data['is_default'] ?? false),
            'confirms_estimate' => $isEstimate && (bool) ($data['confirms_estimate'] ?? false),
            'rejects_to_estimate' => ! $isEstimate && (bool) ($data['rejects_to_estimate'] ?? false),
            'is_post_confirm_default' => ! $isEstimate && (bool) ($data['is_post_confirm_default'] ?? false),
            'sets_sent_at' => $isEstimate && (bool) ($data['sets_sent_at'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function clearExclusiveFlags(string $kind, array $payload, ?int $exceptId): void
    {
        if ($payload['is_default']) {
            $this->clearFlag($kind, 'is_default', $exceptId);
        }

        if ($payload['confirms_estimate']) {
            $this->clearFlag($kind, 'confirms_estimate', $exceptId);
        }

        if ($payload['rejects_to_estimate']) {
            $this->clearFlag($kind, 'rejects_to_estimate', $exceptId);
        }

        if ($payload['is_post_confirm_default']) {
            $this->clearFlag($kind, 'is_post_confirm_default', $exceptId);
        }
    }

    private function clearFlag(string $kind, string $column, ?int $exceptId): void
    {
        WorkOrderStatus::query()
            ->kind($kind)
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->where($column, true)
            ->update([$column => false]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function syncTransitions(WorkOrderStatus $status, array $rows): void
    {
        $kind = $status->kind instanceof WorkOrderStage ? $status->kind->value : (string) $status->kind;
        $kept = [];

        foreach ($rows as $row) {
            if (! is_array($row) || ! filled($row['to_status_id'] ?? null)) {
                continue;
            }

            $toId = (int) $row['to_status_id'];

            if ($toId === (int) $status->id) {
                continue;
            }

            $target = WorkOrderStatus::query()->whereKey($toId)->first();
            $targetKind = $target?->kind instanceof WorkOrderStage
                ? $target->kind->value
                : (string) ($target?->kind ?? '');

            if ($target === null || $targetKind !== $kind) {
                continue;
            }

            $transition = WorkOrderStatusTransition::query()->updateOrCreate(
                [
                    'from_status_id' => $status->id,
                    'to_status_id' => $toId,
                ],
                [
                    'requires_confirmation' => (bool) ($row['requires_confirmation'] ?? false),
                    'requires_justification' => (bool) ($row['requires_justification'] ?? false),
                ],
            );
            $kept[] = $transition->id;
        }

        WorkOrderStatusTransition::query()
            ->where('from_status_id', $status->id)
            ->when($kept !== [], fn ($query) => $query->whereNotIn('id', $kept))
            ->delete();
    }
}
