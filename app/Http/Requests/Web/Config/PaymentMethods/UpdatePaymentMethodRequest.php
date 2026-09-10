<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\PaymentMethods;

use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;

final class UpdatePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PaymentMethod $payment_method */
        $payment_method = $this->route('payment_method');

        return $this->user()?->can('update', $payment_method) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'due_count' => filled($this->input('due_count')) ? $this->integer('due_count') : null,
            'days' => filled($this->input('days')) ? $this->integer('days') : null,
            'code' => filled($this->input('code')) ? trim((string) $this->input('code')) : null,
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
            'due_count' => ['nullable', 'integer', 'min:0', 'max:365'],
            'days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'code' => ['nullable', 'string', 'max:64'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
