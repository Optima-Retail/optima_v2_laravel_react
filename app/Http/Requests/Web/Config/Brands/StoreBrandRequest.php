<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\Brands;

use App\Domain\Companies\Support\ActiveCompany;
use App\Domain\Companies\Support\CompanyMemberUsers;
use App\Models\Brand;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Brand::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => strtoupper(trim((string) $this->input('name'))),
            'account_manager_id' => $this->filled('account_manager_id') ? $this->integer('account_manager_id') : null,
            'commercial_manager_id' => $this->filled('commercial_manager_id') ? $this->integer('commercial_manager_id') : null,
            'collaborator_ids' => array_values(array_filter(
                (array) $this->input('collaborator_ids', []),
                fn (mixed $id): bool => $id !== '' && $id !== null,
            )),
            'loyalty_meeting_frequency' => $this->filled('loyalty_meeting_frequency')
                ? trim((string) $this->input('loyalty_meeting_frequency'))
                : null,
            'is_quality_control_contactable' => $this->boolean('is_quality_control_contactable', true),
            'send_debt_reminders' => $this->boolean('send_debt_reminders', true),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $ownerId = app(ActiveCompany::class)->forUser($this->user())?->id;
        $memberUser = CompanyMemberUsers::existsRule($ownerId);

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('brands', 'name')->whereNull('deleted_at')],
            'account_manager_id' => ['nullable', 'integer', $memberUser],
            'commercial_manager_id' => ['nullable', 'integer', $memberUser],
            'collaborator_ids' => ['sometimes', 'array'],
            'collaborator_ids.*' => [
                'integer',
                'distinct',
                $memberUser,
            ],
            'loyalty_meeting_frequency' => ['nullable', 'string', 'max:255'],
            'is_quality_control_contactable' => ['required', 'boolean'],
            'send_debt_reminders' => ['required', 'boolean'],
        ];
    }
}
