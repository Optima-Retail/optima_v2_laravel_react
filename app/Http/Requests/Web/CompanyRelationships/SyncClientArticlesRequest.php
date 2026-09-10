<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\CompanyRelationships;

use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Models\Article;
use App\Models\CompanyRelationship;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class SyncClientArticlesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->can('create', Article::class)
            || $user->can('articles.update')
            || $user->can('articles.delete');
    }

    protected function prepareForValidation(): void
    {
        $rows = $this->input('articles');

        if (! is_array($rows)) {
            return;
        }

        $normalized = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = $row['id'] ?? null;
            $articleId = $row['article_id'] ?? null;
            $salePrice = $row['sale_price'] ?? null;

            $normalized[] = [
                'id' => $id === null || $id === '' ? null : (int) $id,
                'article_id' => $articleId === null || $articleId === '' ? null : (int) $articleId,
                'sale_price' => $salePrice === null || $salePrice === '' ? null : $salePrice,
            ];
        }

        $this->merge(['articles' => $normalized]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'articles' => ['required', 'array'],
            'articles.*.id' => ['nullable', 'integer'],
            'articles.*.article_id' => [
                'required',
                'integer',
                Rule::exists('articles', 'id')->whereNull('deleted_at'),
            ],
            'articles.*.sale_price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var CompanyRelationship $relationship */
            $relationship = $this->route('relationship');

            if ($relationship->kind !== CompanyRelationshipKind::Customer) {
                $validator->errors()->add('articles', 'Only customer relationships can have article rates.');
            }

            $rows = $this->input('articles', []);
            if (! is_array($rows)) {
                return;
            }

            $seen = [];

            foreach ($rows as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $articleId = (int) ($row['article_id'] ?? 0);
                if ($articleId <= 0) {
                    continue;
                }

                if (isset($seen[$articleId])) {
                    $validator->errors()->add(
                        "articles.{$index}.article_id",
                        __('validation.unique', ['attribute' => 'article']),
                    );
                } else {
                    $seen[$articleId] = true;
                }
            }
        });
    }
}
