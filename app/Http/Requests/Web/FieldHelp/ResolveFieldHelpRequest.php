<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\FieldHelp;

use App\Support\Locale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ResolveFieldHelpRequest extends FormRequest
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
            'keys' => ['required', 'array', 'min:1', 'max:100'],
            'keys.*' => ['required', 'string', 'max:191', 'regex:/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/i'],
            'locale' => ['nullable', 'string', Rule::in(Locale::supported())],
        ];
    }

    protected function prepareForValidation(): void
    {
        $keys = $this->input('keys');
        if (is_string($keys)) {
            $keys = array_filter(array_map('trim', explode(',', $keys)));
            $this->merge(['keys' => array_values($keys)]);
        }
    }
}
