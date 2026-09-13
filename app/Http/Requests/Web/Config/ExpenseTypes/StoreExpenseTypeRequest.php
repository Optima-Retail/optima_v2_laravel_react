<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\ExpenseTypes;

use App\Models\ExpenseType;
use Illuminate\Foundation\Http\FormRequest;

final class StoreExpenseTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ExpenseType::class) ?? false;
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
