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

        return $workOrder instanceof WorkOrder
            && ($this->user()?->can('uploadAttachments', $workOrder) ?? false);
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
