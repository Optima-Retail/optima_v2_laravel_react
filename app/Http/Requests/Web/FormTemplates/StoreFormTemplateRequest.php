<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\FormTemplates;

use App\Domain\Forms\Enums\FormTemplateOwnerType;
use App\Models\FormTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFormTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', FormTemplate::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'form_type_id' => $this->filled('form_type_id') ? $this->integer('form_type_id') : null,
            'language_id' => $this->filled('language_id') ? $this->integer('language_id') : null,
            'work_order_type_id' => $this->filled('work_order_type_id') ? $this->integer('work_order_type_id') : null,
            'brand_id' => $this->filled('brand_id') ? $this->integer('brand_id') : null,
            'company_relationship_id' => $this->filled('company_relationship_id')
                ? $this->integer('company_relationship_id')
                : null,
            'establishment_id' => $this->filled('establishment_id') ? $this->integer('establishment_id') : null,
            'form_bible_id' => $this->filled('form_bible_id') ? $this->integer('form_bible_id') : null,
            'is_default' => $this->boolean('is_default'),
            'establishment_ids' => array_values(array_map(
                static fn ($id): int => (int) $id,
                $this->input('establishment_ids', []) ?: [],
            )),
            'sections' => $this->input('sections', []) ?: [],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $ownerType = $this->string('owner_type')->toString();

        return [
            'name' => ['required', 'string', 'max:255'],
            'form_type_id' => ['required', 'integer', 'exists:form_types,id'],
            'language_id' => ['nullable', 'integer', 'exists:languages,id'],
            'is_default' => ['boolean'],
            'work_order_type_id' => ['nullable', 'integer', 'exists:work_order_types,id'],
            'owner_type' => ['required', 'string', Rule::in(FormTemplateOwnerType::values())],
            'brand_id' => [
                Rule::requiredIf($ownerType === FormTemplateOwnerType::Brand->value),
                'nullable',
                'integer',
                'exists:brands,id',
            ],
            'company_relationship_id' => [
                Rule::requiredIf($ownerType === FormTemplateOwnerType::Customer->value),
                'nullable',
                'integer',
                'exists:company_relationships,id',
            ],
            'establishment_id' => [
                Rule::requiredIf($ownerType === FormTemplateOwnerType::Establishment->value),
                'nullable',
                'integer',
                'exists:establishments,id',
            ],
            'form_bible_id' => [
                Rule::requiredIf($ownerType === FormTemplateOwnerType::Bible->value),
                'nullable',
                'integer',
                'exists:form_bibles,id',
            ],
            'establishment_ids' => ['array'],
            'establishment_ids.*' => ['integer', 'exists:establishments,id'],
            'sections' => ['array'],
            'sections.*.id' => ['nullable', 'integer'],
            'sections.*.label' => ['nullable', 'string', 'max:255'],
            'sections.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'sections.*.is_repeatable' => ['boolean'],
            'sections.*.is_modal' => ['boolean'],
            'sections.*.is_visible' => ['boolean'],
            'sections.*.fields' => ['array'],
            'sections.*.fields.*.id' => ['nullable', 'integer'],
            'sections.*.fields.*.type' => ['required', 'string', 'max:64'],
            'sections.*.fields.*.label' => ['nullable', 'string', 'max:255'],
            'sections.*.fields.*.default_value' => ['nullable', 'string'],
            'sections.*.fields.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'sections.*.fields.*.is_required' => ['boolean'],
            'sections.*.fields.*.is_repeatable' => ['boolean'],
            'sections.*.fields.*.is_visible' => ['boolean'],
            'sections.*.fields.*.is_locked' => ['boolean'],
        ];
    }
}
