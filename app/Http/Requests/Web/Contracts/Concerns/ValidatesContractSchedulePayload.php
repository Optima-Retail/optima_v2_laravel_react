<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Contracts\Concerns;

use App\Domain\Contracts\Enums\ContractInvoicingAggregationFrequency;
use App\Domain\Contracts\Enums\ContractIterationPeriodicity;
use App\Domain\Contracts\Enums\ContractIterationPeriodicityKind;
use App\Models\Company;
use App\Models\Contract;
use Illuminate\Validation\Rule;

trait ValidatesContractSchedulePayload
{
    /**
     * @return array<string, mixed>
     */
    protected function scheduleRules(Company $owner, ?Contract $contract = null): array
    {
        $companyId = $this->integer('company_id');
        $establishmentRule = Rule::exists('establishments', 'id')->whereNull('deleted_at');
        if ($companyId > 0) {
            $establishmentRule = $establishmentRule->where('company_id', $companyId);
        }

        $aggregationIdRule = Rule::exists('contract_invoicing_aggregations', 'id')->whereNull('deleted_at');
        if ($contract !== null) {
            $aggregationIdRule = $aggregationIdRule->where('contract_id', $contract->id);
        }

        $formTemplateRule = Rule::exists('form_templates', 'id')
            ->whereNull('deleted_at')
            ->where('company_id', $owner->id);

        return [
            'iterations' => ['nullable', 'array'],
            'iterations.*.id' => ['nullable', 'integer'],
            'iterations.*.temp_key' => ['nullable', 'string', 'max:64'],
            'iterations.*.subject' => ['nullable', 'string', 'max:200'],
            'iterations.*.work_order_type_id' => [
                'required',
                'integer',
                Rule::exists('work_order_types', 'id')->whereNull('deleted_at'),
            ],
            'iterations.*.starts_on' => ['required', 'date'],
            'iterations.*.ends_on' => ['required', 'date', 'after_or_equal:iterations.*.starts_on'],
            'iterations.*.periodicity' => ['required', Rule::in(ContractIterationPeriodicity::values())],
            'iterations.*.periodicity_kind' => ['required', Rule::in(ContractIterationPeriodicityKind::values())],
            'iterations.*.interval' => ['nullable', 'integer', 'min:1'],
            'iterations.*.weekdays' => ['nullable', 'array'],
            'iterations.*.weekdays.*' => ['integer', 'min:1', 'max:7'],
            'iterations.*.month_days' => ['nullable', 'array'],
            'iterations.*.month_days.*' => ['integer', 'min:1', 'max:31'],
            'iterations.*.months' => ['nullable', 'array'],
            'iterations.*.months.*' => ['integer', 'min:1', 'max:12'],
            'iterations.*.cost_amount' => ['required', 'numeric', 'min:0'],
            'iterations.*.establishment_ids' => ['nullable', 'array'],
            'iterations.*.establishment_ids.*' => ['integer', $establishmentRule],
            'iterations.*.form_template_id' => ['nullable', 'integer', $formTemplateRule],
            'iterations.*.invoicing_aggregation_id' => ['nullable', 'integer', $aggregationIdRule],
            'iterations.*.invoicing_aggregation_temp_key' => ['nullable', 'string', 'max:64'],

            'invoicing_aggregations' => ['nullable', 'array'],
            'invoicing_aggregations.*.id' => ['nullable', 'integer', $aggregationIdRule],
            'invoicing_aggregations.*.temp_key' => ['nullable', 'string', 'max:64'],
            'invoicing_aggregations.*.subject' => ['nullable', 'string', 'max:200'],
            'invoicing_aggregations.*.billing_frequency' => [
                'nullable',
                Rule::in(ContractInvoicingAggregationFrequency::values()),
            ],
            'invoicing_aggregations.*.billing_day' => ['nullable', 'integer', 'min:1', 'max:28'],
            'invoicing_aggregations.*.billing_cycle_start' => ['nullable', 'date'],
            'invoicing_aggregations.*.per_establishment' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareScheduleForValidation(): void
    {
        $iterations = collect((array) $this->input('iterations', []))
            ->map(function (mixed $row): array {
                $row = is_array($row) ? $row : [];

                return [
                    'id' => filled($row['id'] ?? null) ? (int) $row['id'] : null,
                    'temp_key' => filled($row['temp_key'] ?? null) ? (string) $row['temp_key'] : null,
                    'subject' => filled($row['subject'] ?? null) ? (string) $row['subject'] : null,
                    'work_order_type_id' => filled($row['work_order_type_id'] ?? null) ? (int) $row['work_order_type_id'] : null,
                    'starts_on' => filled($row['starts_on'] ?? null) ? (string) $row['starts_on'] : null,
                    'ends_on' => filled($row['ends_on'] ?? null) ? (string) $row['ends_on'] : null,
                    'periodicity' => filled($row['periodicity'] ?? null) ? (string) $row['periodicity'] : null,
                    'periodicity_kind' => filled($row['periodicity_kind'] ?? null) ? (string) $row['periodicity_kind'] : null,
                    'interval' => filled($row['interval'] ?? null) ? (int) $row['interval'] : null,
                    'weekdays' => $this->intList($row['weekdays'] ?? []),
                    'month_days' => $this->intList($row['month_days'] ?? []),
                    'months' => $this->intList($row['months'] ?? []),
                    'cost_amount' => filled($row['cost_amount'] ?? null) ? $row['cost_amount'] : null,
                    'establishment_ids' => $this->intList($row['establishment_ids'] ?? []),
                    'form_template_id' => filled($row['form_template_id'] ?? null) ? (int) $row['form_template_id'] : null,
                    'invoicing_aggregation_id' => filled($row['invoicing_aggregation_id'] ?? null)
                        ? (int) $row['invoicing_aggregation_id']
                        : null,
                    'invoicing_aggregation_temp_key' => filled($row['invoicing_aggregation_temp_key'] ?? null)
                        ? (string) $row['invoicing_aggregation_temp_key']
                        : null,
                ];
            })
            ->values()
            ->all();

        $aggregations = collect((array) $this->input('invoicing_aggregations', []))
            ->map(function (mixed $row): array {
                $row = is_array($row) ? $row : [];

                return [
                    'id' => filled($row['id'] ?? null) ? (int) $row['id'] : null,
                    'temp_key' => filled($row['temp_key'] ?? null) ? (string) $row['temp_key'] : null,
                    'subject' => filled($row['subject'] ?? null) ? (string) $row['subject'] : null,
                    'billing_frequency' => filled($row['billing_frequency'] ?? null)
                        ? (string) $row['billing_frequency']
                        : ContractInvoicingAggregationFrequency::Monthly->value,
                    'billing_day' => filled($row['billing_day'] ?? null) ? (int) $row['billing_day'] : null,
                    'billing_cycle_start' => filled($row['billing_cycle_start'] ?? null)
                        ? (string) $row['billing_cycle_start']
                        : null,
                    'per_establishment' => filter_var($row['per_establishment'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ];
            })
            ->values()
            ->all();

        $this->merge([
            'iterations' => $iterations,
            'invoicing_aggregations' => $aggregations,
        ]);
    }

    /**
     * @param  mixed  $values
     * @return list<int>
     */
    private function intList(mixed $values): array
    {
        return array_values(array_filter(
            array_map('intval', (array) $values),
            fn (int $id) => $id > 0,
        ));
    }
}
