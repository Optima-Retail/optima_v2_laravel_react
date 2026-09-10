<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\Articles;

use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Models\Article;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Article::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_deletable' => $this->boolean('is_deletable', true),
            'translations' => array_values((array) $this->input('translations', [])),
            'clients' => array_values((array) $this->input('clients', [])),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('articles', 'code')->whereNull('deleted_at')],
            'is_deletable' => ['required', 'boolean'],
            'translations' => ['required', 'array', 'min:1'],
            'translations.*.language_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('languages', 'id')->whereNull('deleted_at'),
            ],
            'translations.*.name' => ['required', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string', 'max:5000'],
            'clients' => ['nullable', 'array'],
            'clients.*.company_relationship_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('company_relationships', 'id')
                    ->whereNull('deleted_at')
                    ->where('kind', CompanyRelationshipKind::Customer->value),
            ],
            'clients.*.sale_price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
        ];
    }
}
