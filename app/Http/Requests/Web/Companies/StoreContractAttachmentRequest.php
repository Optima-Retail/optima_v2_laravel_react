<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Companies;

use App\Models\Contract;
use Illuminate\Foundation\Http\FormRequest;

final class StoreContractAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Contract|null $contract */
        $contract = $this->route('contract');

        return $contract instanceof Contract
            && ($this->user()?->can('uploadAttachments', $contract) ?? false);
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
