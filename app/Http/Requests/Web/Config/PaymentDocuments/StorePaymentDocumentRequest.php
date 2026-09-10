<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\PaymentDocuments;

use App\Models\PaymentDocument;
use Illuminate\Foundation\Http\FormRequest;

final class StorePaymentDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PaymentDocument::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
