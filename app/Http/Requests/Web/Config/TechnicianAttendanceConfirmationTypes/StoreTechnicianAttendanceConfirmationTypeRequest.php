<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\TechnicianAttendanceConfirmationTypes;

use App\Models\TechnicianAttendanceConfirmationType;
use Illuminate\Foundation\Http\FormRequest;

final class StoreTechnicianAttendanceConfirmationTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', TechnicianAttendanceConfirmationType::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
