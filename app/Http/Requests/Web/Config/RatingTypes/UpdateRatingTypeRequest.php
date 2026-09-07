<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\RatingTypes;

use App\Models\RatingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateRatingTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var RatingType $ratingType */
        $ratingType = $this->route('rating_type');

        return $this->user()?->can('update', $ratingType) ?? false;
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
        /** @var RatingType $ratingType */
        $ratingType = $this->route('rating_type');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:64',
                'alpha_dash',
                Rule::unique('rating_types', 'code')
                    ->whereNull('deleted_at')
                    ->ignore($ratingType->id),
            ],
            'max_score' => ['required', 'integer', 'min:1', 'max:1000'],
        ];
    }
}
