<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\WorkOrders;

use App\Models\WorkOrder;
use Illuminate\Foundation\Http\FormRequest;

final class StoreWorkOrderAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var WorkOrder|null $workOrder */
        $workOrder = $this->route('work_order');
        $user = $this->user();

        if ($user === null || ! ($workOrder instanceof WorkOrder)) {
            return false;
        }

        if (! $user->can('uploadAttachments', $workOrder)) {
            return false;
        }

        if ($this->boolean('is_private') && ! $user->can('viewPrivateAttachments', $workOrder)) {
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
