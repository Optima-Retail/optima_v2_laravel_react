<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\TechnicianAttendanceConfirmationTypes;

use App\Models\TechnicianAttendanceConfirmationType;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateTechnicianAttendanceConfirmationTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var TechnicianAttendanceConfirmationType $type */
        $type = $this->route('confirmation_type');

        return $this->user()?->can('update', $type) ?? false;
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
