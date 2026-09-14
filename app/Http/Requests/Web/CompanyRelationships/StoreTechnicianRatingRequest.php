<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\CompanyRelationships;

use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\Companies\Enums\TechnicianRatingSource;
use App\Models\CompanyRelationship;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreTechnicianRatingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        /** @var CompanyRelationship $relationship */
        $relationship = $this->route('relationship');

        if ($relationship->kind !== CompanyRelationshipKind::Technician) {
            abort(404);
        }

        return $user->can('update', $relationship);
    }

    protected function prepareForValidation(): void
    {
        $workOrderId = $this->input('work_order_id');
        $notes = $this->input('notes');

        $this->merge([
            'work_order_id' => $workOrderId === null || $workOrderId === '' ? null : (int) $workOrderId,
            'notes' => $notes === null || $notes === '' ? null : $notes,
            'score' => $this->filled('score') ? (int) $this->input('score') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'score' => ['required', 'integer', 'min:0', 'max:10'],
            'notes' => ['nullable', 'string'],
            'source' => ['required', 'string', Rule::in(TechnicianRatingSource::values())],
            'work_order_id' => ['nullable', 'integer', Rule::exists('work_orders', 'id')->whereNull('deleted_at')],
        ];
    }
}
