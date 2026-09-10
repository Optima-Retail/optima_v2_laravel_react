<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\PaymentDocuments;

use App\Models\PaymentDocument;
use Illuminate\Foundation\Http\FormRequest;

final class UpdatePaymentDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PaymentDocument $payment_document */
        $payment_document = $this->route('payment_document');

        return $this->user()?->can('update', $payment_document) ?? false;
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
