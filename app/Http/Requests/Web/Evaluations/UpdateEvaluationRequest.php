<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Evaluations;

use App\Domain\Companies\Support\ActiveCompany;
use App\Domain\Companies\Support\CompanyMemberUsers;
use App\Domain\Evaluations\Services\EvaluationService;
use App\Models\Evaluation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Evaluation $evaluation */
        $evaluation = $this->route('evaluation');

        return $this->user()?->can('update', $evaluation) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'subject' => filled($this->input('subject')) ? $this->input('subject') : null,
            'establishment_id' => filled($this->input('establishment_id')) ? $this->integer('establishment_id') : null,
            'evaluation_status_id' => filled($this->input('evaluation_status_id')) ? $this->integer('evaluation_status_id') : null,
            'responsible_user_id' => filled($this->input('responsible_user_id')) ? $this->integer('responsible_user_id') : null,
            'next_action_at' => filled($this->input('next_action_at')) ? $this->input('next_action_at') : null,
            'facility_question' => filled($this->input('facility_question')) ? $this->input('facility_question') : null,
            'technician_question' => filled($this->input('technician_question')) ? $this->input('technician_question') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $owner = app(ActiveCompany::class)->forUser($this->user());
        abort_if($owner === null, 403);

        $service = app(EvaluationService::class);
        $accessible = $service->accessibleCompanyIds($owner);
        $companyIds = $accessible === [] ? [0] : $accessible;

        $statusRule = Rule::exists('evaluation_statuses', 'id')->whereNull('deleted_at');

        /** @var Evaluation $evaluation */
        $evaluation = $this->route('evaluation');
        $currentStatusId = $evaluation->evaluation_status_id !== null ? (int) $evaluation->evaluation_status_id : null;

        if ($currentStatusId !== null && $this->integer('evaluation_status_id') === $currentStatusId) {
            // Allow keeping the existing status even if it is no longer open.
        } else {
            $statusRule = $statusRule->where('is_open', true);
        }

        return [
            'subject' => ['nullable', 'string', 'max:255'],
            'establishment_id' => [
                'required',
                'integer',
                Rule::exists('establishments', 'id')
                    ->whereNull('deleted_at')
                    ->whereIn('company_id', $companyIds),
            ],
            'evaluation_status_id' => ['nullable', 'integer', $statusRule],
            'responsible_user_id' => ['nullable', 'integer', CompanyMemberUsers::existsRule($owner->id)],
            'next_action_at' => ['nullable', 'date'],
            'facility_question' => ['nullable', 'string', 'max:255'],
            'technician_question' => ['nullable', 'string', 'max:255'],
        ];
    }
}
