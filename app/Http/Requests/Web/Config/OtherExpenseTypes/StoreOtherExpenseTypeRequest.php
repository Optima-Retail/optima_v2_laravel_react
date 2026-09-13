<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\OtherExpenseTypes;

use App\Models\OtherExpenseType;
use Illuminate\Foundation\Http\FormRequest;

final class StoreOtherExpenseTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', OtherExpenseType::class) ?? false;
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
