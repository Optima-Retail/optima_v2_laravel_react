<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\TechnicianRequestStatuses;

use App\Domain\TechnicianRequests\Enums\TechnicianRequestStatusKind;
use App\Models\TechnicianRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreTechnicianRequestStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', TechnicianRequestStatus::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'color' => filled($this->input('color')) ? $this->input('color') : null,
            'lifecycle' => filled($this->input('lifecycle')) ? $this->input('lifecycle') : null,
            'is_open' => $this->boolean('is_open'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', 'string', Rule::in(TechnicianRequestStatusKind::values())],
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:32', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'lifecycle' => ['nullable', 'integer', 'min:0', 'max:255'],
            'is_open' => ['required', 'boolean'],
        ];
    }
}
