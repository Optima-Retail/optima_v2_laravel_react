<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\CompanyRelationships;

use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Models\ClientRate;
use App\Models\CompanyRelationship;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class SyncClientRatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        /** @var CompanyRelationship $relationship */
        $relationship = $this->route('relationship');

        if ($relationship->kind !== CompanyRelationshipKind::Customer) {
            abort(404);
        }

        return $user->can('create', ClientRate::class)
            || $user->can('client_rates.update')
            || $user->can('client_rates.delete');
    }

    protected function prepareForValidation(): void
    {
        $rows = $this->input('rates');

        if (! is_array($rows)) {
            return;
        }

        $normalized = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = $row['id'] ?? null;
            $priorityId = $row['client_priority_id'] ?? null;
            $workOrderTypeId = $row['work_order_type_id'] ?? null;

            $normalized[] = [
                'id' => $id === null || $id === '' ? null : (int) $id,
                'client_priority_id' => $priorityId === null || $priorityId === '' ? null : (int) $priorityId,
                'work_order_type_id' => $workOrderTypeId === null || $workOrderTypeId === '' ? null : (int) $workOrderTypeId,
                'travel_amount' => $row['travel_amount'] ?? 0,
                'extra_travel_amount' => $row['extra_travel_amount'] ?? 0,
                'labor_amount' => $row['labor_amount'] ?? 0,
                'extra_labor_amount' => $row['extra_labor_amount'] ?? 0,
                'due_hours' => $row['due_hours'] ?? 0,
                'sla_hours' => $row['sla_hours'] ?? 0,
                'is_urgent' => filter_var($row['is_urgent'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        $this->merge(['rates' => $normalized]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rates' => ['present', 'array'],
            'rates.*.id' => ['nullable', 'integer'],
            'rates.*.client_priority_id' => [
                'required',
                'integer',
                Rule::exists('client_priorities', 'id')->whereNull('deleted_at'),
            ],
            'rates.*.work_order_type_id' => [
                'required',
                'integer',
                Rule::exists('work_order_types', 'id')->whereNull('deleted_at'),
            ],
            'rates.*.travel_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'rates.*.extra_travel_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'rates.*.labor_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'rates.*.extra_labor_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'rates.*.due_hours' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'rates.*.sla_hours' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'rates.*.is_urgent' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $rows = $this->input('rates', []);
            if (! is_array($rows)) {
                return;
            }

            $seen = [];

            foreach ($rows as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $priorityId = (int) ($row['client_priority_id'] ?? 0);
                $workOrderTypeId = (int) ($row['work_order_type_id'] ?? 0);

                if ($priorityId <= 0 || $workOrderTypeId <= 0) {
                    continue;
                }

                $key = $priorityId.':'.$workOrderTypeId;

                if (isset($seen[$key])) {
                    $validator->errors()->add(
                        "rates.{$index}.client_priority_id",
                        __('validation.unique', ['attribute' => 'priority / work order type']),
                    );
                } else {
                    $seen[$key] = true;
                }
            }
        });
    }
}
