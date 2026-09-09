<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Companies;

use App\Http\Requests\Web\Companies\Concerns\ValidatesIncidentPayload;
use App\Models\Incident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class StoreIncidentRequest extends FormRequest
{
    use ValidatesIncidentPayload;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Incident::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareIncidentPayload();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->incidentRules(statusMustBeOpen: true);
    }

    public function withValidator(Validator $validator): void
    {
        $this->afterIncidentValidation($validator);
    }
}
