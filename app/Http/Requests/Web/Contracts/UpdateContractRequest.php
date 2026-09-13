<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Contracts;

use App\Domain\Companies\Support\ActiveCompany;
use App\Domain\Companies\Support\CompanyMemberUsers;
use App\Domain\Contracts\Services\ContractService;
use App\Http\Requests\Web\Contracts\Concerns\ValidatesContractSchedulePayload;
use App\Models\Contract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateContractRequest extends FormRequest
{
    use ValidatesContractSchedulePayload;

    public function authorize(): bool
    {
        /** @var Contract $contract */
        $contract = $this->route('contract');

        return $this->user()?->can('update', $contract) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => filled($this->input('code')) ? $this->input('code') : null,
            'description' => filled($this->input('description')) ? $this->input('description') : null,
            'work_order_subject' => filled($this->input('work_order_subject')) ? $this->input('work_order_subject') : null,
            'company_id' => filled($this->input('company_id')) ? $this->integer('company_id') : null,
            'responsible_user_id' => filled($this->input('responsible_user_id')) ? $this->integer('responsible_user_id') : null,
            'contract_status_id' => filled($this->input('contract_status_id')) ? $this->integer('contract_status_id') : null,
            'language_id' => filled($this->input('language_id')) ? $this->integer('language_id') : null,
            'signed_at' => filled($this->input('signed_at')) ? $this->input('signed_at') : null,
            'canceled_at' => filled($this->input('canceled_at')) ? $this->input('canceled_at') : null,
            'establishment_ids' => array_values(array_filter(
                array_map('intval', (array) $this->input('establishment_ids', [])),
                fn (int $id) => $id > 0,
            )),
        ]);

        $this->prepareScheduleForValidation();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $owner = app(ActiveCompany::class)->forUser($this->user());
        abort_if($owner === null, 403);

        $service = app(ContractService::class);
        $accessible = $service->accessibleCompanyIds($owner);
        $companyIds = $accessible === [] ? [0] : $accessible;

        $establishmentRule = Rule::exists('establishments', 'id')->whereNull('deleted_at');
        if ($this->integer('company_id') > 0) {
            $establishmentRule = $establishmentRule->where('company_id', $this->integer('company_id'));
        }

        $statusRule = Rule::exists('contract_statuses', 'id')->whereNull('deleted_at');

        /** @var Contract $contract */
        $contract = $this->route('contract');
        $currentStatusId = $contract->contract_status_id !== null ? (int) $contract->contract_status_id : null;

        if ($currentStatusId !== null && $this->integer('contract_status_id') === $currentStatusId) {
            // Allow keeping the existing status even if it is no longer active.
        } else {
            $statusRule = $statusRule->where('is_open', true);
        }

        return [
            'code' => ['nullable', 'string', 'max:64'],
            'description' => ['required', 'string', 'max:255'],
            'work_order_subject' => ['nullable', 'string', 'max:255'],
            'company_id' => ['required', 'integer', Rule::in($companyIds)],
            'responsible_user_id' => ['required', 'integer', CompanyMemberUsers::existsRule($owner->id)],
            'contract_status_id' => ['required', 'integer', $statusRule],
            'language_id' => ['nullable', 'integer', Rule::exists('languages', 'id')->whereNull('deleted_at')],
            'signed_at' => ['nullable', 'date'],
            'canceled_at' => ['nullable', 'date'],
            'establishment_ids' => ['nullable', 'array'],
            'establishment_ids.*' => ['integer', $establishmentRule],
            ...$this->scheduleRules($owner, $contract),
        ];
    }
}
