<?php

declare(strict_types=1);

namespace App\Domain\Config\ClientRates\Services;

use App\Models\ClientPriority;
use App\Models\ClientRate;
use App\Models\CompanyRelationship;
use App\Models\WorkOrderType;
use Illuminate\Support\Facades\DB;

final class ClientRateService
{
    /**
     * @return list<array{
     *     id: int,
     *     client_priority_id: int,
     *     client_priority_label: string,
     *     work_order_type_id: int,
     *     work_order_type_label: string,
     *     travel_amount: string,
     *     extra_travel_amount: string,
     *     labor_amount: string,
     *     extra_labor_amount: string,
     *     due_hours: int,
     *     sla_hours: int,
     *     is_urgent: bool
     * }>
     */
    public function forClient(CompanyRelationship $relationship): array
    {
        return ClientRate::query()
            ->with(['clientPriority:id,name,code', 'workOrderType:id,name,code'])
            ->where('company_relationship_id', $relationship->id)
            ->orderBy('client_priority_id')
            ->orderBy('work_order_type_id')
            ->get()
            ->map(fn (ClientRate $rate): array => $this->toRow($rate))
            ->values()
            ->all();
    }

    /**
     * Replace the client's rate matrix with the given rows.
     *
     * Soft-deletes removed combinations; updateOrCreate (including trashed) by priority + work order type.
     *
     * @param  list<array{
     *     id?: int|null,
     *     client_priority_id: int,
     *     work_order_type_id: int,
     *     travel_amount?: float|int|string|null,
     *     extra_travel_amount?: float|int|string|null,
     *     labor_amount?: float|int|string|null,
     *     extra_labor_amount?: float|int|string|null,
     *     due_hours?: int|string|null,
     *     sla_hours?: int|string|null,
     *     is_urgent?: bool|int|string|null
     * }>  $rows
     * @return list<array{
     *     id: int,
     *     client_priority_id: int,
     *     client_priority_label: string,
     *     work_order_type_id: int,
     *     work_order_type_label: string,
     *     travel_amount: string,
     *     extra_travel_amount: string,
     *     labor_amount: string,
     *     extra_labor_amount: string,
     *     due_hours: int,
     *     sla_hours: int,
     *     is_urgent: bool
     * }>
     */
    public function syncForClient(CompanyRelationship $relationship, array $rows): array
    {
        return DB::transaction(function () use ($relationship, $rows): array {
            $normalized = [];

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $priorityId = (int) ($row['client_priority_id'] ?? 0);
                $workOrderTypeId = (int) ($row['work_order_type_id'] ?? 0);

                if ($priorityId <= 0 || $workOrderTypeId <= 0) {
                    continue;
                }

                $key = $priorityId.':'.$workOrderTypeId;
                $normalized[$key] = [
                    'client_priority_id' => $priorityId,
                    'work_order_type_id' => $workOrderTypeId,
                    'travel_amount' => $row['travel_amount'] ?? 0,
                    'extra_travel_amount' => $row['extra_travel_amount'] ?? 0,
                    'labor_amount' => $row['labor_amount'] ?? 0,
                    'extra_labor_amount' => $row['extra_labor_amount'] ?? 0,
                    'due_hours' => (int) ($row['due_hours'] ?? 0),
                    'sla_hours' => (int) ($row['sla_hours'] ?? 0),
                    'is_urgent' => filter_var($row['is_urgent'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ];
            }

            $existing = ClientRate::query()
                ->where('company_relationship_id', $relationship->id)
                ->get();

            foreach ($existing as $rate) {
                $key = $rate->client_priority_id.':'.$rate->work_order_type_id;
                if (! isset($normalized[$key])) {
                    $rate->delete();
                }
            }

            foreach ($normalized as $content) {
                /** @var ClientRate|null $rate */
                $rate = ClientRate::withTrashed()
                    ->where('company_relationship_id', $relationship->id)
                    ->where('client_priority_id', $content['client_priority_id'])
                    ->where('work_order_type_id', $content['work_order_type_id'])
                    ->first();

                if ($rate !== null) {
                    if ($rate->trashed()) {
                        $rate->restore();
                    }

                    $rate->update($content);
                } else {
                    ClientRate::query()->create([
                        'company_relationship_id' => $relationship->id,
                        ...$content,
                    ]);
                }
            }

            return $this->forClient($relationship);
        });
    }

    /**
     * Options for the client rates carousel editor (selected priorities × all work order types).
     *
     * @return array{
     *     selected_priorities: list<array{id: int, name: string, color: string|null}>,
     *     work_order_types: list<array{id: int, name: string, color: string|null, label: string}>
     * }
     */
    public function editorOptions(CompanyRelationship $relationship): array
    {
        $company = $relationship->relatedCompany;

        $selectedPriorities = $company === null
            ? []
            : $company->priorities()
                ->orderBy('client_priorities.level')
                ->orderBy('client_priorities.name')
                ->get(['client_priorities.id', 'client_priorities.name', 'client_priorities.color'])
                ->map(fn (ClientPriority $priority): array => [
                    'id' => $priority->id,
                    'name' => $priority->name,
                    'color' => $priority->color,
                ])
                ->values()
                ->all();

        $workOrderTypes = WorkOrderType::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'color'])
            ->map(fn (WorkOrderType $type): array => [
                'id' => $type->id,
                'name' => $type->name,
                'color' => $type->color,
                'label' => $type->code
                    ? "{$type->code} — {$type->name}"
                    : $type->name,
            ])
            ->values()
            ->all();

        return [
            'selected_priorities' => $selectedPriorities,
            'work_order_types' => $workOrderTypes,
        ];
    }

    /**
     * @return array{
     *     priority_options: list<array{id: int, label: string}>,
     *     work_order_type_options: list<array{id: int, label: string}>
     * }
     */
    public function formOptions(): array
    {
        return [
            'priority_options' => ClientPriority::query()
                ->orderBy('level')
                ->orderBy('name')
                ->get(['id', 'name', 'code'])
                ->map(fn (ClientPriority $priority): array => [
                    'id' => $priority->id,
                    'label' => $priority->code
                        ? "{$priority->code} — {$priority->name}"
                        : $priority->name,
                ])
                ->values()
                ->all(),
            'work_order_type_options' => WorkOrderType::query()
                ->orderBy('name')
                ->get(['id', 'name', 'code'])
                ->map(fn (WorkOrderType $type): array => [
                    'id' => $type->id,
                    'label' => $type->code
                        ? "{$type->code} — {$type->name}"
                        : $type->name,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     client_priority_id: int,
     *     client_priority_label: string,
     *     work_order_type_id: int,
     *     work_order_type_label: string,
     *     travel_amount: string,
     *     extra_travel_amount: string,
     *     labor_amount: string,
     *     extra_labor_amount: string,
     *     due_hours: int,
     *     sla_hours: int,
     *     is_urgent: bool
     * }
     */
    private function toRow(ClientRate $rate): array
    {
        $priority = $rate->clientPriority;
        $workOrderType = $rate->workOrderType;

        $priorityLabel = $priority
            ? ($priority->code ? "{$priority->code} — {$priority->name}" : $priority->name)
            : '';
        $workOrderTypeLabel = $workOrderType
            ? ($workOrderType->code ? "{$workOrderType->code} — {$workOrderType->name}" : $workOrderType->name)
            : '';

        return [
            'id' => $rate->id,
            'client_priority_id' => $rate->client_priority_id,
            'client_priority_label' => $priorityLabel,
            'work_order_type_id' => $rate->work_order_type_id,
            'work_order_type_label' => $workOrderTypeLabel,
            'travel_amount' => (string) $rate->travel_amount,
            'extra_travel_amount' => (string) $rate->extra_travel_amount,
            'labor_amount' => (string) $rate->labor_amount,
            'extra_labor_amount' => (string) $rate->extra_labor_amount,
            'due_hours' => (int) $rate->due_hours,
            'sla_hours' => (int) $rate->sla_hours,
            'is_urgent' => (bool) $rate->is_urgent,
        ];
    }
}
