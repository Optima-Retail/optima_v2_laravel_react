<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Forms;

use App\Domain\Forms\Enums\FormSubjectType;
use App\Models\Form;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Form::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'form_template_id' => $this->filled('form_template_id') ? $this->integer('form_template_id') : null,
            'form_type_id' => $this->filled('form_type_id') ? $this->integer('form_type_id') : null,
            'form_status_id' => $this->filled('form_status_id') ? $this->integer('form_status_id') : null,
            'language_id' => $this->filled('language_id') ? $this->integer('language_id') : null,
            'work_order_id' => $this->filled('work_order_id') ? $this->integer('work_order_id') : null,
            'company_relationship_id' => $this->filled('company_relationship_id')
                ? $this->integer('company_relationship_id')
                : null,
            'app_platform_id' => $this->filled('app_platform_id') ? $this->integer('app_platform_id') : 1,
            'sections' => $this->input('sections', []) ?: [],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $subjectType = $this->string('subject_type')->toString();

        return [
            'name' => ['nullable', 'string', 'max:255'],
            'form_template_id' => ['nullable', 'integer', 'exists:form_templates,id'],
            'form_type_id' => ['nullable', 'integer', 'exists:form_types,id'],
            'form_status_id' => ['nullable', 'integer', 'exists:form_statuses,id'],
            'language_id' => ['nullable', 'integer', 'exists:languages,id'],
            'subject_type' => ['required', 'string', Rule::in(FormSubjectType::values())],
            'work_order_id' => [
                Rule::requiredIf($subjectType === FormSubjectType::WorkOrder->value),
                'nullable',
                'integer',
                'exists:work_orders,id',
            ],
            'company_relationship_id' => [
                Rule::requiredIf($subjectType === FormSubjectType::Technician->value),
                'nullable',
                'integer',
                'exists:company_relationships,id',
            ],
            'occurred_on' => ['nullable', 'date'],
            'app_platform_id' => ['nullable', 'integer', 'in:1,2,3'],
            'technician_code' => ['nullable', 'string', 'max:255'],
            'sections' => ['array'],
            'sections.*.id' => ['nullable', 'integer'],
            'sections.*.label' => ['nullable', 'string', 'max:255'],
            'sections.*.fields' => ['array'],
            'sections.*.fields.*.id' => ['nullable', 'integer'],
            'sections.*.fields.*.type' => ['required_with:sections', 'string', 'max:64'],
            'sections.*.fields.*.label' => ['nullable', 'string', 'max:255'],
            'sections.*.fields.*.value' => ['nullable', 'string'],
        ];
    }
}
