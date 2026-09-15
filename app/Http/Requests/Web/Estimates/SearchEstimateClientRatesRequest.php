<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Estimates;

use App\Policies\EstimatePolicy;
use Illuminate\Foundation\Http\FormRequest;

final class SearchEstimateClientRatesRequest extends FormRequest
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
            'work_order_type_id' => ['required', 'integer', 'exists:work_order_types,id'],
        ];
    }
}
