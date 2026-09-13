<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\SavedFilters;

use App\Domain\SavedFilters\Enums\SavedFilterPageKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreSavedFilterRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'page_key' => ['required', 'string', Rule::in(SavedFilterPageKey::values())],
            'filters' => ['required', 'array'],
            'filters.*' => ['nullable'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
