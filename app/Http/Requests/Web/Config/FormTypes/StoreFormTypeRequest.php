<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\FormTypes;

use App\Models\FormType;
use Illuminate\Foundation\Http\FormRequest;

final class StoreFormTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', FormType::class) ?? false;
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
