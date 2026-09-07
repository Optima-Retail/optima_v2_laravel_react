<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\Timezones;

use App\Models\Timezone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateTimezoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Timezone $timezone */
        $timezone = $this->route('timezone');

        return $this->user()?->can('update', $timezone) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge([
                'name' => trim((string) $this->input('name')),
            ]);
        }

        if ($this->has('timezone')) {
            $this->merge([
                'timezone' => trim((string) $this->input('timezone')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Timezone $timezone */
        $timezone = $this->route('timezone');

        return [
            'name' => ['required', 'string', 'max:255'],
            'timezone' => [
                'required',
                'string',
                'max:255',
                Rule::unique('timezones', 'timezone')
                    ->whereNull('deleted_at')
                    ->ignore($timezone->id),
            ],
        ];
    }
}
