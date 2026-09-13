<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\FormBibles;

use App\Models\FormBible;
use Illuminate\Foundation\Http\FormRequest;

final class StoreFormBibleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', FormBible::class) ?? false;
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
