<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\SwitchCompany;

use Illuminate\Foundation\Http\FormRequest;

final class SwitchCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
        ];
    }
}
