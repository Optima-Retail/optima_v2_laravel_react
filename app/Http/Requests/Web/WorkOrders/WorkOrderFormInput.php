<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\WorkOrders;

use App\Domain\Companies\Support\CompanyMemberUsers;
use App\Domain\WorkOrders\Enums\WorkOrderStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class WorkOrderFormInput
{
    /**
     * @return array<string, mixed>
     */
    public static function prepare(FormRequest $request): array
    {
        $lines = array_values(array_map(
            static function (mixed $line): array {
                $row = is_array($line) ? $line : [];

                return [
                    'id' => filled($row['id'] ?? null) ? (int) $row['id'] : null,
                    'article_id' => filled($row['article_id'] ?? null) ? (int) $row['article_id'] : null,
                    'description' => filled($row['description'] ?? null) ? (string) $row['description'] : null,
                    'quantity' => filled($row['quantity'] ?? null) ? $row['quantity'] : 1,
                    'unit_price' => filled($row['unit_price'] ?? null) ? $row['unit_price'] : 0,
                ];
            },
            (array) $request->input('lines', []),
        ));

        $technicians = array_values(array_filter(array_map(
            static function (mixed $row): ?array {
                $item = is_array($row) ? $row : [];

                if (! filled($item['company_relationship_id'] ?? null)) {
                    return null;
                }

                return [
                    'id' => filled($item['id'] ?? null) ? (int) $item['id'] : null,
                    'company_relationship_id' => (int) $item['company_relationship_id'],
                    'is_selected' => filter_var($item['is_selected'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'quote_net_amount' => filled($item['quote_net_amount'] ?? null) ? $item['quote_net_amount'] : null,
                ];
            },
            (array) $request->input('technicians', []),
        )));

        return [
            'code' => filled($request->input('code')) ? $request->input('code') : null,
            'subject' => filled($request->input('subject')) ? $request->input('subject') : null,
            'reference' => filled($request->input('reference')) ? $request->input('reference') : null,
            'purchase_order' => filled($request->input('purchase_order')) ? $request->input('purchase_order') : null,
            'status_id' => filled($request->input('status_id')) ? $request->integer('status_id') : null,
            'work_order_type_id' => filled($request->input('work_order_type_id')) ? $request->integer('work_order_type_id') : null,
            'client_priority_id' => filled($request->input('client_priority_id')) ? $request->integer('client_priority_id') : null,
            'is_urgent' => $request->boolean('is_urgent'),
            'establishment_id' => filled($request->input('establishment_id')) ? $request->integer('establishment_id') : null,
            'responsible_user_id' => filled($request->input('responsible_user_id')) ? $request->integer('responsible_user_id') : null,
            'requester_id' => filled($request->input('requester_id')) ? $request->integer('requester_id') : null,
            'notes' => filled($request->input('notes')) ? $request->input('notes') : null,
            'internal_notes' => filled($request->input('internal_notes')) ? $request->input('internal_notes') : null,
            'received_at' => filled($request->input('received_at')) ? $request->input('received_at') : null,
            'intervention_at' => filled($request->input('intervention_at')) ? $request->input('intervention_at') : null,
            'due_at' => filled($request->input('due_at')) ? $request->input('due_at') : null,
            'collaborator_ids' => array_values(array_filter(
                array_map('intval', (array) $request->input('collaborator_ids', [])),
                fn (int $id): bool => $id > 0,
            )),
            'lines' => $lines,
            'technicians' => $technicians,
        ];
    }

    /**
     * @param  list<int>  $establishmentIds
     * @return array<string, mixed>
     */
    public static function baseRules(int $ownerCompanyId, array $establishmentIds, string $stage): array
    {
        $statusKind = $stage === WorkOrderStage::WorkOrder->value
            ? WorkOrderStage::WorkOrder->value
            : WorkOrderStage::Estimate->value;

        return [
            'subject' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'purchase_order' => ['nullable', 'string', 'max:255'],
            'status_id' => [
                'required',
                'integer',
                Rule::exists('work_order_statuses', 'id')->whereNull('deleted_at')->where('kind', $statusKind),
            ],
            'work_order_type_id' => ['nullable', 'integer', Rule::exists('work_order_types', 'id')->whereNull('deleted_at')],
            'client_priority_id' => ['nullable', 'integer', Rule::exists('client_priorities', 'id')->whereNull('deleted_at')],
            'is_urgent' => ['required', 'boolean'],
            'establishment_id' => ['required', 'integer', Rule::in($establishmentIds)],
            'responsible_user_id' => ['nullable', 'integer', CompanyMemberUsers::existsRule($ownerCompanyId)],
            'requester_id' => ['nullable', 'integer', Rule::exists('requesters', 'id')->whereNull('deleted_at')],
            'notes' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
            'received_at' => ['nullable', 'date'],
            'intervention_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
            'collaborator_ids' => ['nullable', 'array'],
            'collaborator_ids.*' => ['integer', CompanyMemberUsers::existsRule($ownerCompanyId)],
            'lines' => ['nullable', 'array'],
            'lines.*.id' => ['nullable', 'integer'],
            'lines.*.article_id' => ['nullable', 'integer', Rule::exists('articles', 'id')->whereNull('deleted_at')],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0'],
            'lines.*.unit_price' => ['required', 'numeric'],
            'technicians' => ['nullable', 'array'],
            'technicians.*.id' => ['nullable', 'integer'],
            'technicians.*.company_relationship_id' => [
                'required',
                'integer',
                Rule::exists('company_relationships', 'id')->whereNull('deleted_at')->where('owner_company_id', $ownerCompanyId),
            ],
            'technicians.*.is_selected' => ['required', 'boolean'],
            'technicians.*.quote_net_amount' => ['nullable', 'numeric'],
        ];
    }
}
