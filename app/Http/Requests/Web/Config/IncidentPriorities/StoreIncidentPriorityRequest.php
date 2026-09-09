<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\IncidentPriorities;

use App\Models\IncidentPriority;
use Illuminate\Foundation\Http\FormRequest;

final class StoreIncidentPriorityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', IncidentPriority::class) ?? false;
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:32', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'resolution_time_hours' => ['required', 'integer', 'min:1', 'max:8760'],
        ];
    }
}
