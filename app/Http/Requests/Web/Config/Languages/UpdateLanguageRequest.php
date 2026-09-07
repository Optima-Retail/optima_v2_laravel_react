<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\Languages;

use App\Models\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateLanguageRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Language $language */
        $language = $this->route('language');

        return $this->user()?->can('update', $language) ?? false;
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
        /** @var Language $language */
        $language = $this->route('language');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:64',
                'alpha_dash',
                Rule::unique('languages', 'code')
                    ->whereNull('deleted_at')
                    ->ignore($language->id),
            ],
        ];
    }
}
