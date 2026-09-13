<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\TechnicianRequests;

use App\Domain\TechnicianRequests\Enums\TechnicianRequestStatusKind;
use App\Models\TechnicianRequest;
use App\Models\TechnicianRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class StoreTechnicianRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', TechnicianRequest::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_screening' => $this->boolean('is_screening'),
            'work_order_id' => filled($this->input('work_order_id')) ? $this->input('work_order_id') : null,
            'country_id' => filled($this->input('country_id')) ? $this->input('country_id') : null,
            'language_id' => filled($this->input('language_id')) ? $this->input('language_id') : null,
            'responsible_user_id' => filled($this->input('responsible_user_id')) ? $this->input('responsible_user_id') : null,
            'technician_request_priority_id' => filled($this->input('technician_request_priority_id'))
                ? $this->input('technician_request_priority_id')
                : null,
            'technician_request_status_id' => filled($this->input('technician_request_status_id'))
                ? $this->input('technician_request_status_id')
                : null,
            'due_at' => filled($this->input('due_at')) ? $this->input('due_at') : null,
            'next_action_at' => filled($this->input('next_action_at')) ? $this->input('next_action_at') : null,
            'service_type_ids' => $this->input('service_type_ids', []),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'is_screening' => ['required', 'boolean'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'province_name' => ['nullable', 'string', 'max:255'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'language_id' => ['nullable', 'integer', 'exists:languages,id'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'work_order_id' => ['nullable', 'integer', 'exists:work_orders,id'],
            'due_at' => ['nullable', 'date'],
            'next_action_at' => ['nullable', 'date'],
            'technician_request_priority_id' => ['nullable', 'integer', 'exists:technician_request_priorities,id'],
            'technician_request_status_id' => ['nullable', 'integer', 'exists:technician_request_statuses,id'],
            'service_type_ids' => ['nullable', 'array'],
            'service_type_ids.*' => ['integer', 'exists:service_types,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $statusId = $this->input('technician_request_status_id');

            if ($statusId === null) {
                return;
            }

            $status = TechnicianRequestStatus::query()->find((int) $statusId);

            if ($status === null) {
                return;
            }

            $expectedKind = $this->boolean('is_screening')
                ? TechnicianRequestStatusKind::Screening
                : TechnicianRequestStatusKind::Request;

            if ($status->kind !== $expectedKind) {
                $validator->errors()->add(
                    'technician_request_status_id',
                    'The selected status does not match the request kind.',
                );
            }
        });
    }
}
