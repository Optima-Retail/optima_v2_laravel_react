<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\TechnicianRequests;

use App\Models\TechnicianRequest;
use Illuminate\Foundation\Http\FormRequest;

final class AttachTechnicianRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var TechnicianRequest $technicianRequest */
        $technicianRequest = $this->route('technician_request');

        return $this->user()?->can('update', $technicianRequest) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_relationship_id' => ['required', 'integer', 'exists:company_relationships,id'],
        ];
    }
}
