<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\Currencies;

use App\Models\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Currency $currency */
        $currency = $this->route('currency');

        return $this->user()?->can('update', $currency) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge(['name' => trim((string) $this->input('name'))]);
        }

        if ($this->has('code')) {
            $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Currency $currency */
        $currency = $this->route('currency');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:16',
                'alpha_num',
                Rule::unique('currencies', 'code')->whereNull('deleted_at')->ignore($currency->id),
            ],
        ];
    }
}
