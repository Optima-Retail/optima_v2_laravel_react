<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Chats;

use Illuminate\Foundation\Http\FormRequest;

final class StoreDocumentChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:50000'],
            'is_private' => ['sometimes', 'boolean'],
        ];
    }
}
