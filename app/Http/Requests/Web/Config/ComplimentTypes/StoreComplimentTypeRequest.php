<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\ComplimentTypes;

use App\Models\ComplimentType;
use Illuminate\Foundation\Http\FormRequest;

final class StoreComplimentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ComplimentType::class) ?? false;
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
