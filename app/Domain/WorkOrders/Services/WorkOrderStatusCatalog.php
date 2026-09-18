<?php

declare(strict_types=1);

namespace App\Domain\WorkOrders\Services;

use App\Domain\WorkOrders\Enums\WorkOrderStage;
use App\Models\WorkOrderStatus;
use App\Models\WorkOrderStatusTransition;

final class WorkOrderStatusCatalog
{
    public function defaultId(WorkOrderStage $kind): ?int
    {
        $id = WorkOrderStatus::query()
            ->kind($kind)
            ->default()
            ->orderBy('lifecycle')
            ->orderBy('id')
            ->value('id');

        if ($id !== null) {
            return (int) $id;
        }

        $fallback = WorkOrderStatus::query()
            ->kind($kind)
            ->where('is_open', true)
            ->orderBy('lifecycle')
            ->orderBy('id')
            ->value('id');

        return $fallback !== null ? (int) $fallback : null;
    }

    public function confirmsEstimateId(): ?int
    {
        return $this->firstId(WorkOrderStage::Estimate, 'confirms_estimate');
    }

    public function rejectsToEstimateId(): ?int
    {
        return $this->firstId(WorkOrderStage::WorkOrder, 'rejects_to_estimate');
    }

    public function postConfirmDefaultId(): ?int
    {
        $id = $this->firstId(WorkOrderStage::WorkOrder, 'is_post_confirm_default');

        if ($id !== null) {
            return $id;
        }

        return $this->defaultId(WorkOrderStage::WorkOrder);
    }

    public function statusConfirmsEstimate(?int $statusId): bool
    {
        return $this->flagIsTrue($statusId, 'confirms_estimate');
    }

    public function statusRejectsToEstimate(?int $statusId): bool
    {
        return $this->flagIsTrue($statusId, 'rejects_to_estimate');
    }

    public function statusSetsSentAt(?int $statusId): bool
    {
        return $this->flagIsTrue($statusId, 'sets_sent_at');
    }

    public function isOpen(?int $statusId): bool
    {
        if ($statusId === null) {
            return true;
        }

        $value = WorkOrderStatus::query()->whereKey($statusId)->value('is_open');

        return $value === null ? true : (bool) $value;
    }

    /**
     * Status IDs for pending/closed list filters (avoids whereHas on every row).
     *
     * @return list<int>
     */
    public function idsByOpen(bool $isOpen): array
    {
        return WorkOrderStatus::query()
            ->where('is_open', $isOpen)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Null means no matrix is configured for this origin (any same-kind status is allowed).
     *
     * @return list<int>|null
     */
    public function allowedTargetIds(int $fromStatusId): ?array
    {
        $ids = WorkOrderStatusTransition::query()
            ->where('from_status_id', $fromStatusId)
            ->pluck('to_status_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return null;
        }

        $ids[] = $fromStatusId;

        return array_values(array_unique($ids));
    }

    public function transition(int $fromStatusId, int $toStatusId): ?WorkOrderStatusTransition
    {
        return WorkOrderStatusTransition::query()
            ->where('from_status_id', $fromStatusId)
            ->where('to_status_id', $toStatusId)
            ->first();
    }

    public function canTransition(int $fromStatusId, int $toStatusId): bool
    {
        if ($fromStatusId === $toStatusId) {
            return true;
        }

        $allowed = $this->allowedTargetIds($fromStatusId);

        if ($allowed === null) {
            return true;
        }

        return in_array($toStatusId, $allowed, true);
    }

    /**
     * @return list<array{
     *     id: int,
     *     label: string,
     *     color: string|null,
     *     is_open: bool,
     *     confirms_estimate: bool,
     *     rejects_to_estimate: bool,
     *     requires_confirmation: bool,
     *     requires_justification: bool
     * }>
     */
    public function options(WorkOrderStage $kind, ?int $currentStatusId = null): array
    {
        $query = WorkOrderStatus::query()->kind($kind)->orderBy('lifecycle')->orderBy('id');

        if ($currentStatusId !== null) {
            $allowed = $this->allowedTargetIds($currentStatusId);

            if ($allowed !== null) {
                $query->whereIn('id', $allowed);
            }
        }

        $statuses = $query->get();
        $edges = $currentStatusId === null
            ? collect()
            : WorkOrderStatusTransition::query()
                ->where('from_status_id', $currentStatusId)
                ->get()
                ->keyBy(fn (WorkOrderStatusTransition $row): int => (int) $row->to_status_id);

        return $statuses
            ->map(function (WorkOrderStatus $status) use ($currentStatusId, $edges): array {
                $id = (int) $status->id;
                $edge = $currentStatusId !== null && $id !== $currentStatusId
                    ? $edges->get($id)
                    : null;

                return [
                    'id' => $id,
                    'label' => $status->name,
                    'color' => $status->color,
                    'lifecycle' => $status->lifecycle !== null ? (int) $status->lifecycle : null,
                    'is_open' => (bool) $status->is_open,
                    'confirms_estimate' => (bool) $status->confirms_estimate,
                    'rejects_to_estimate' => (bool) $status->rejects_to_estimate,
                    'requires_confirmation' => (bool) ($edge?->requires_confirmation ?? false),
                    'requires_justification' => (bool) ($edge?->requires_justification ?? false),
                ];
            })
            ->values()
            ->all();
    }

    private function firstId(WorkOrderStage $kind, string $column): ?int
    {
        $id = WorkOrderStatus::query()
            ->kind($kind)
            ->where($column, true)
            ->orderBy('lifecycle')
            ->orderBy('id')
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    private function flagIsTrue(?int $statusId, string $column): bool
    {
        if ($statusId === null) {
            return false;
        }

        return WorkOrderStatus::query()->whereKey($statusId)->where($column, true)->exists();
    }
}
