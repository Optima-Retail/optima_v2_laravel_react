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

        if ($user === null || ! ($estimate instanceof WorkOrder)) {
            return false;
        }

        $policy = app(EstimatePolicy::class);

        if (! $policy->uploadAttachments($user, $estimate)) {
            return false;
        }

        if ($this->boolean('is_private') && ! $policy->viewPrivateAttachments($user, $estimate)) {
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
