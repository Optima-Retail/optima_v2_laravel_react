<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Forms;

use App\Models\Form;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreFormFieldFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Form|null $form */
        $form = $this->route('form');

        return $form instanceof Form && ($this->user()?->can('update', $form) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'image', 'max:8192'],
            'target' => ['required', 'string', Rule::in(['value', 'before', 'after'])],
        ];
    }
}
