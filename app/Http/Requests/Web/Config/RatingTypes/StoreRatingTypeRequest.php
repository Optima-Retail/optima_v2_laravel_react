<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\RatingTypes;

use App\Models\RatingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreRatingTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', RatingType::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge([
                'code' => strtolower(trim((string) $this->input('code'))),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:64',
                'alpha_dash',
                Rule::unique('rating_types', 'code')->whereNull('deleted_at'),
            ],
            'max_score' => ['required', 'integer', 'min:1', 'max:1000'],
        ];
    }
}
