<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Estimates;

use App\Domain\WorkOrders\Services\TechnicianSearchService;
use App\Policies\EstimatePolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SearchEstimateTechniciansRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (
            app(EstimatePolicy::class)->create($user)
            || app(EstimatePolicy::class)->viewAny($user)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'establishment_id' => ['required', 'integer', 'exists:establishments,id'],
            'work_order_type_id' => ['nullable', 'integer', 'exists:work_order_types,id'],
            'tab' => ['nullable', 'string', Rule::in([
                TechnicianSearchService::TAB_NEARBY,
                TechnicianSearchService::TAB_HISTORY,
                TechnicianSearchService::TAB_RATING,
                TechnicianSearchService::TAB_ALL,
            ])],
            'search' => ['nullable', 'string', 'max:255'],
            'service_type_ids' => ['nullable', 'array'],
            'service_type_ids.*' => ['integer', 'exists:service_types,id'],
            'radius_km' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'prl_ok' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:50'],
        ];
    }
}
