<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\ComplimentTypes;

use App\Models\ComplimentType;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateComplimentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ComplimentType $complimentType */
        $complimentType = $this->route('compliment_type');

        return $this->user()?->can('update', $complimentType) ?? false;
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
