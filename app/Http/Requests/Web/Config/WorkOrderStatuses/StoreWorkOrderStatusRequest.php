<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\WorkOrderStatuses;

use App\Domain\WorkOrders\Enums\WorkOrderStage;
use App\Models\WorkOrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreWorkOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', WorkOrderStatus::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'color' => filled($this->input('color')) ? $this->input('color') : null,
            'lifecycle' => filled($this->input('lifecycle')) ? $this->input('lifecycle') : null,
            'is_open' => $this->boolean('is_open'),
            'is_default' => $this->boolean('is_default'),
            'confirms_estimate' => $this->boolean('confirms_estimate'),
            'rejects_to_estimate' => $this->boolean('rejects_to_estimate'),
            'is_post_confirm_default' => $this->boolean('is_post_confirm_default'),
            'sets_sent_at' => $this->boolean('sets_sent_at'),
            'transitions' => $this->normalizedTransitions(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', 'string', Rule::in(WorkOrderStage::values())],
            'color' => ['nullable', 'string', 'max:32', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'lifecycle' => ['nullable', 'integer', 'min:0', 'max:255'],
            'is_open' => ['required', 'boolean'],
            'is_default' => ['required', 'boolean'],
            'confirms_estimate' => ['required', 'boolean'],
            'rejects_to_estimate' => ['required', 'boolean'],
            'is_post_confirm_default' => ['required', 'boolean'],
            'sets_sent_at' => ['required', 'boolean'],
            'transitions' => ['nullable', 'array'],
            'transitions.*.to_status_id' => ['required', 'integer', Rule::exists('work_order_statuses', 'id')->whereNull('deleted_at')],
            'transitions.*.requires_confirmation' => ['required', 'boolean'],
            'transitions.*.requires_justification' => ['required', 'boolean'],
        ];
    }

    /**
     * @return list<array{to_status_id: int, requires_confirmation: bool, requires_justification: bool}>
     */
    private function normalizedTransitions(): array
    {
        $rows = [];

        foreach ((array) $this->input('transitions', []) as $row) {
            if (! is_array($row) || ! filled($row['to_status_id'] ?? null)) {
                continue;
            }

            $rows[] = [
                'to_status_id' => (int) $row['to_status_id'],
                'requires_confirmation' => filter_var($row['requires_confirmation'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'requires_justification' => filter_var($row['requires_justification'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        return $rows;
    }
}
