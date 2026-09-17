<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Establishments;

use App\Domain\Companies\Services\EstablishmentService;
use App\Domain\Companies\Support\ActiveCompany;
use App\Domain\Companies\Support\CompanyValidation;
use App\Models\Establishment;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateEstablishmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Establishment $establishment */
        $establishment = $this->route('establishment');

        return $this->user()?->can('update', $establishment) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(CompanyValidation::blankToNull($this->all(), [
            'code', 'store_code', 'alternate_store_code', 'phone', 'email', 'emails', 'recipient_emails',
            'address_line_1', 'address_line_2', 'city', 'province_id', 'postal_code',
            'country_id', 'timezone_id', 'language_id', 'establishment_type_id', 'delegation_id', 'series_id',
            'billing_company_id', 'responsible_user_id', 'company_id',
            'latitude', 'longitude', 'tax_rate', 'integration_external_id',
            'notes', 'internal_notes',
        ]));

        $booleans = [];
        foreach (CompanyValidation::establishmentBooleanKeys() as $key) {
            if ($key === 'is_active' || $this->has($key)) {
                $booleans[$key] = $this->boolean($key, $key === 'is_active');
            }
        }

        $this->merge([
            ...$booleans,
            'collaborator_ids' => array_values(array_filter(
                (array) $this->input('collaborator_ids', []),
                fn (mixed $id): bool => $id !== '' && $id !== null,
            )),
            'blocked_technician_ids' => array_values(array_filter(
                (array) $this->input('blocked_technician_ids', []),
                fn (mixed $id): bool => $id !== '' && $id !== null,
            )),
            'favorite_technician_ids' => array_values(array_filter(
                (array) $this->input('favorite_technician_ids', []),
                fn (mixed $id): bool => $id !== '' && $id !== null,
            )),
        ]);

        if ($this->has('form_template_links')) {
            $this->merge([
                'form_template_links' => array_values(array_map(
                    function (mixed $row): array {
                        $row = is_array($row) ? $row : [];

                        return [
                            'id' => $row['id'] ?? null,
                            'form_template_id' => $row['form_template_id'] ?? null,
                            'work_order_type_id' => $row['work_order_type_id'] ?? null,
                        ];
                    },
                    (array) $this->input('form_template_links', []),
                )),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Establishment $establishment */
        $establishment = $this->route('establishment');
        $owner = app(ActiveCompany::class)->forUser($this->user());

        abort_if($owner === null, 403);

        $accessible = app(EstablishmentService::class)->accessibleCompanyIds($owner);

        return [
            ...CompanyValidation::establishmentRules($establishment->id, $accessible),
            'form_template_links' => ['sometimes', 'array'],
            'form_template_links.*.id' => ['nullable', 'integer', 'exists:establishment_form_template,id'],
            'form_template_links.*.form_template_id' => ['required', 'integer', 'exists:form_templates,id'],
            'form_template_links.*.work_order_type_id' => ['required', 'integer', 'exists:work_order_types,id'],
        ];
    }
}
