<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Compliments;

use App\Domain\Compliments\Enums\ComplimentSubjectType;
use App\Models\Compliment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreComplimentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Compliment::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'brand_id' => $this->filled('brand_id') ? $this->integer('brand_id') : null,
            'company_relationship_id' => $this->filled('company_relationship_id')
                ? $this->integer('company_relationship_id')
                : null,
            'establishment_id' => $this->filled('establishment_id')
                ? $this->integer('establishment_id')
                : null,
            'compliment_type_id' => $this->filled('compliment_type_id')
                ? $this->integer('compliment_type_id')
                : null,
            'score' => $this->filled('score') ? $this->integer('score') : null,
            'user_ids' => array_values(array_map(
                static fn ($id): int => (int) $id,
                $this->input('user_ids', []) ?: [],
            )),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $subjectType = $this->string('subject_type')->toString();

        return [
            'subject_type' => ['required', 'string', Rule::in(ComplimentSubjectType::values())],
            'brand_id' => [
                Rule::requiredIf($subjectType === ComplimentSubjectType::Brand->value),
                'nullable',
                'integer',
                'exists:brands,id',
            ],
            'company_relationship_id' => [
                Rule::requiredIf($subjectType === ComplimentSubjectType::Customer->value),
                'nullable',
                'integer',
                'exists:company_relationships,id',
            ],
            'establishment_id' => [
                Rule::requiredIf($subjectType === ComplimentSubjectType::Establishment->value),
                'nullable',
                'integer',
                'exists:establishments,id',
            ],
            'compliment_type_id' => ['required', 'integer', 'exists:compliment_types,id'],
            'comment' => ['nullable', 'string'],
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'score' => ['required', 'integer', 'min:1'],
            'file' => ['nullable', 'file', 'max:20480'],
        ];
    }
}
