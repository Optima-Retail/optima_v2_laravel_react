<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\ClientPriorities;

use App\Models\ClientPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreClientPriorityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ClientPriority::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => filled($this->input('code')) ? $this->input('code') : null,
            'color' => filled($this->input('color')) ? $this->input('color') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('client_priorities', 'code')->whereNull('deleted_at'),
            ],
            'color' => ['nullable', 'string', 'max:32', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'level' => ['required', 'integer', 'min:1', 'max:10'],
        ];
    }
}
