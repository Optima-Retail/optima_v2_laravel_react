<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\OtherExpenseTypes;

use App\Models\OtherExpenseType;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateOtherExpenseTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var OtherExpenseType $otherExpenseType */
        $otherExpenseType = $this->route('other_expense_type');

        return $this->user()?->can('update', $otherExpenseType) ?? false;
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
