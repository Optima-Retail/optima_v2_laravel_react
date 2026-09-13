<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\Checklists;

use App\Domain\Config\Checklists\Enums\ChecklistDocumentType;
use App\Models\Checklist;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreChecklistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Checklist::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'requires_validation' => $this->boolean('requires_validation'),
            'sort_order' => filled($this->input('sort_order')) ? $this->input('sort_order') : 0,
            'work_order_status_id' => filled($this->input('work_order_status_id'))
                ? $this->integer('work_order_status_id')
                : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $documentType = (string) $this->input('document_type');

        return [
            'label' => ['required', 'string'],
            'requires_validation' => ['required', 'boolean'],
            'document_type' => ['required', 'string', Rule::in(ChecklistDocumentType::values())],
            'work_order_status_id' => [
                'required',
                'integer',
                Rule::exists('work_order_statuses', 'id')
                    ->whereNull('deleted_at')
                    ->where('kind', $documentType),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
