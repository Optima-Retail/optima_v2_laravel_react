<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\FormBibles;

use App\Models\FormBible;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateFormBibleRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var FormBible|null $bible */
        $bible = $this->route('form_bible');

        return $bible instanceof FormBible
            && ($this->user()?->can('update', $bible) ?? false);
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
