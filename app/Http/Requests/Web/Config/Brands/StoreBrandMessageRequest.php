<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\Brands;

use App\Models\Brand;
use Illuminate\Foundation\Http\FormRequest;

final class StoreBrandMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $brand = $this->route('brand');

        if ($user === null || ! $brand instanceof Brand) {
            return false;
        }

        if ($this->hasFile('file')) {
            return $user->can('sendMessageFiles', $brand);
        }

        return $user->can('sendMessages', $brand);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:10000'],
            'file' => ['nullable', 'file', 'max:20480'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->hasFile('file')) {
                return;
            }

            $plain = trim(strip_tags(str_replace("\n", '', (string) $this->input('body'))));

            if ($plain === '') {
                $validator->errors()->add('body', __('validation.required', ['attribute' => 'body']));
            }
        });
    }
}
