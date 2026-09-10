<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\FormTypes;

use App\Models\FormType;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateFormTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var FormType $formType */
        $formType = $this->route('form_type');

        return $this->user()?->can('update', $formType) ?? false;
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
