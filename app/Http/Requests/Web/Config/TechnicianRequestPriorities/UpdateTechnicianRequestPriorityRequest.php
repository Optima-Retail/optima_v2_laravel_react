<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\TechnicianRequestPriorities;

use App\Domain\TechnicianRequests\Enums\TechnicianRequestPriorityKey;
use App\Models\TechnicianRequestPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateTechnicianRequestPriorityRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var TechnicianRequestPriority $technicianRequestPriority */
        $technicianRequestPriority = $this->route('technician_request_priority');

        return $this->user()?->can('update', $technicianRequestPriority) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'color' => filled($this->input('color')) ? $this->input('color') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var TechnicianRequestPriority $technicianRequestPriority */
        $technicianRequestPriority = $this->route('technician_request_priority');

        return [
            'name' => ['required', 'string', 'max:255'],
            'key' => [
                'required',
                'string',
                Rule::in(TechnicianRequestPriorityKey::values()),
                Rule::unique('technician_request_priorities', 'key')
                    ->ignore($technicianRequestPriority->id),
            ],
            'color' => ['nullable', 'string', 'max:32', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
        ];
    }
}
