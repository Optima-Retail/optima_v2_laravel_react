<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\ExpenseTypes;

use App\Models\ExpenseType;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateExpenseTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ExpenseType $expenseType */
        $expenseType = $this->route('expense_type');

        return $this->user()?->can('update', $expenseType) ?? false;
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
