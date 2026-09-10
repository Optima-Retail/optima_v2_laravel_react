<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\FormStatuses;

use App\Models\FormStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreFormStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', FormStatus::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'next_status_id' => [
                'nullable',
                'integer',
                Rule::exists('form_statuses', 'id')->whereNull('deleted_at'),
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'next_status_id' => ($this->input('next_status_id') === '' || $this->input('next_status_id') === null)
                ? null
                : $this->input('next_status_id'),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }
}
