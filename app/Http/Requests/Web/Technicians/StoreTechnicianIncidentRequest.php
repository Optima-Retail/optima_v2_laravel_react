<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Technicians;

use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\Companies\Support\ActiveCompany;
use App\Domain\Companies\Support\CompanyMemberUsers;
use App\Models\TechnicianIncident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreTechnicianIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', TechnicianIncident::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $owner = app(ActiveCompany::class)->forUser($this->user());
        $ownerId = $owner?->id;

        return [
            'technician_id' => [
                'required',
                'integer',
                Rule::exists('company_relationships', 'id')->where(
                    function ($query) use ($ownerId) {
                        $query->where('kind', CompanyRelationshipKind::Technician->value);

                        if ($ownerId !== null) {
                            $query->where('owner_company_id', $ownerId);
                        } else {
                            $query->whereRaw('1 = 0');
                        }
                    },
                ),
            ],
            'technician_incident_type_id' => ['required', 'integer', 'exists:technician_incident_types,id'],
            'responded_by_id' => ['required', 'integer', CompanyMemberUsers::existsRule($ownerId)],
            'incident_text' => ['required', 'string', 'max:65535'],
        ];
    }
}
