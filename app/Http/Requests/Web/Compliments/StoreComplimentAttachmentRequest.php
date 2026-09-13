<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Compliments;

use App\Models\Compliment;
use Illuminate\Foundation\Http\FormRequest;

final class StoreComplimentAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Compliment|null $compliment */
        $compliment = $this->route('compliment');

        return $compliment instanceof Compliment
            && ($this->user()?->can('uploadAttachments', $compliment) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:20480'],
        ];
    }
}
