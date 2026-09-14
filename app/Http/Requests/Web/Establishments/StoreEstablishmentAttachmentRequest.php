<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Establishments;

use App\Models\Establishment;
use Illuminate\Foundation\Http\FormRequest;

final class StoreEstablishmentAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Establishment|null $establishment */
        $establishment = $this->route('establishment');

        if (! $establishment instanceof Establishment) {
            return false;
        }

        $user = $this->user();

        if ($user === null || ! $user->can('uploadAttachments', $establishment)) {
            return false;
        }

        if ($this->boolean('is_private') && ! $user->can('viewPrivateAttachments', $establishment)) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:20480'],
            'is_private' => ['sometimes', 'boolean'],
        ];
    }
}
