<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\CompanyRelationships;

use App\Models\CompanyRelationship;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateCompanyScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CompanyRelationship $relationship */
        $relationship = $this->route('relationship');

        return $this->user()?->can('update', $relationship) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach ([
            'monday_opens', 'monday_closes',
            'tuesday_opens', 'tuesday_closes',
            'wednesday_opens', 'wednesday_closes',
            'thursday_opens', 'thursday_closes',
            'friday_opens', 'friday_closes',
            'saturday_opens', 'saturday_closes',
            'sunday_opens', 'sunday_closes',
        ] as $key) {
            $value = $this->input($key);
            if (is_string($value) && preg_match('/^\d{2}:\d{2}$/', $value) === 1) {
                $normalized[$key] = $value.':00';
            } elseif (filled($value)) {
                $normalized[$key] = $value;
            } else {
                $normalized[$key] = null;
            }
        }

        $this->merge($normalized);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $timeRule = ['nullable', 'date_format:H:i:s'];

        return [
            'monday_opens' => $timeRule,
            'monday_closes' => $timeRule,
            'tuesday_opens' => $timeRule,
            'tuesday_closes' => $timeRule,
            'wednesday_opens' => $timeRule,
            'wednesday_closes' => $timeRule,
            'thursday_opens' => $timeRule,
            'thursday_closes' => $timeRule,
            'friday_opens' => $timeRule,
            'friday_closes' => $timeRule,
            'saturday_opens' => $timeRule,
            'saturday_closes' => $timeRule,
            'sunday_opens' => $timeRule,
            'sunday_closes' => $timeRule,
        ];
    }
}
