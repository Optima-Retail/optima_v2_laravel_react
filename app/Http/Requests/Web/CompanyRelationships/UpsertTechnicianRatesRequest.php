<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\CompanyRelationships;

use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Models\CompanyRelationship;
use Illuminate\Foundation\Http\FormRequest;

final class UpsertTechnicianRatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        /** @var CompanyRelationship $relationship */
        $relationship = $this->route('relationship');

        if ($relationship->kind !== CompanyRelationshipKind::Technician) {
            abort(404);
        }

        return $user->can('update', $relationship);
    }

    protected function prepareForValidation(): void
    {
        $keys = [
            'labor_weekday_amount',
            'labor_night_amount',
            'labor_weekend_amount',
            'labor_holiday_amount',
            'labor_urgent_amount',
            'travel_weekday_amount',
            'travel_night_amount',
            'travel_weekend_amount',
            'travel_holiday_amount',
            'travel_urgent_amount',
        ];

        $normalized = [];

        foreach ($keys as $key) {
            $normalized[$key] = $this->input($key, 0);
        }

        $this->merge($normalized);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $amount = ['nullable', 'numeric', 'min:0', 'max:99999999.99'];

        return [
            'labor_weekday_amount' => $amount,
            'labor_night_amount' => $amount,
            'labor_weekend_amount' => $amount,
            'labor_holiday_amount' => $amount,
            'labor_urgent_amount' => $amount,
            'travel_weekday_amount' => $amount,
            'travel_night_amount' => $amount,
            'travel_weekend_amount' => $amount,
            'travel_holiday_amount' => $amount,
            'travel_urgent_amount' => $amount,
        ];
    }
}
