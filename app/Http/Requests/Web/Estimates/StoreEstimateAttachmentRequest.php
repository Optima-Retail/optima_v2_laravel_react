<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Estimates;

use App\Models\WorkOrder;
use App\Policies\EstimatePolicy;
use Illuminate\Foundation\Http\FormRequest;

final class StoreEstimateAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var WorkOrder|null $estimate */
        $estimate = $this->route('estimate');
        $user = $this->user();

        return $user !== null
            && $estimate instanceof WorkOrder
            && app(EstimatePolicy::class)->uploadAttachments($user, $estimate);
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
