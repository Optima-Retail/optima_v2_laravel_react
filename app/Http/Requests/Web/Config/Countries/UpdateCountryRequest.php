<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\Countries;

use App\Models\Country;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateCountryRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Country $country */
        $country = $this->route('country');

        return $this->user()?->can('update', $country) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge([
                'name' => trim((string) $this->input('name')),
            ]);
        }

        if ($this->has('iso_code')) {
            $iso = strtoupper(trim((string) $this->input('iso_code')));
            $this->merge([
                'iso_code' => $iso === '' ? null : $iso,
            ]);
        }

        if ($this->has('timezone_id') && $this->input('timezone_id') === '') {
            $this->merge(['timezone_id' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'iso_code' => ['nullable', 'string', 'size:2', 'alpha'],
            'timezone_id' => ['nullable', 'integer', Rule::exists('timezones', 'id')->whereNull('deleted_at')],
        ];
    }
}
