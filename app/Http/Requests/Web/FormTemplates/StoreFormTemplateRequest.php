<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\FormTemplates;

use App\Domain\Companies\Support\ActiveCompany;
use App\Domain\Forms\Enums\FormTemplateOwnerType;
use App\Models\FormTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFormTemplateRequest extends FormRequest
{
    /**
     * Field types that describe a structural/collection block rather than a single
     * answer, so a visible label isn't required for them (mirrors legacy's
     * required_unless on materiales/tecnicos/trabajo/cabecera).
     *
     * @var list<string>
     */
    private const LABEL_OPTIONAL_TYPES = ['materiales', 'tecnicos', 'trabajo', 'cabecera'];

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
        $ownerId = app(ActiveCompany::class)->forUser($this->user())?->id;
        $template = $this->route('form_template');
        $templateId = $template instanceof FormTemplate ? $template->id : null;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('form_templates', 'name')
                    ->where(fn ($query) => $query->where('company_id', $ownerId))
                    ->whereNull('deleted_at')
                    ->ignore($templateId),
            ],
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
            'sections.*.fields.*.payload' => ['nullable', 'array'],
            'sections.*.fields.*.payload.value' => ['nullable', 'string', 'max:255'],
            'sections.*.fields.*.conditional_field_id' => [
                'nullable',
                'integer',
                Rule::exists('form_template_fields', 'id')->where(function ($query) use ($templateId): void {
                    $query->whereNull('deleted_at');

                    if ($templateId !== null) {
                        $query->whereIn('form_template_section_id', function ($sub) use ($templateId): void {
                            $sub->select('id')->from('form_template_sections')->where('form_template_id', $templateId);
                        });

                        return;
                    }

                    // A brand-new template has no fields of its own yet, so a field
                    // can only ever depend on a sibling saved in an earlier update.
                    $query->whereRaw('1 = 0');
                }),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ((array) $this->input('sections', []) as $sectionIndex => $section) {
                foreach ((array) ($section['fields'] ?? []) as $fieldIndex => $field) {
                    $type = (string) ($field['type'] ?? '');
                    $label = $field['label'] ?? null;

                    if ($type !== '' && ! in_array($type, self::LABEL_OPTIONAL_TYPES, true) && ($label === null || $label === '')) {
                        $validator->errors()->add(
                            "sections.{$sectionIndex}.fields.{$fieldIndex}.label",
                            __('validation.required', ['attribute' => 'label']),
                        );
                    }

                    $fieldId = $field['id'] ?? null;
                    $conditionalFieldId = $field['conditional_field_id'] ?? null;

                    if ($fieldId !== null && $conditionalFieldId !== null && (int) $fieldId === (int) $conditionalFieldId) {
                        $validator->errors()->add(
                            "sections.{$sectionIndex}.fields.{$fieldIndex}.conditional_field_id",
                            'A field cannot be conditional on itself.',
                        );
                    }
                }
            }
        });
    }
}
