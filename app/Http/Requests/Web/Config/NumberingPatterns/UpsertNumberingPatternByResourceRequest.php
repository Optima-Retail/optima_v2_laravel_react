<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\NumberingPatterns;

use App\Domain\Companies\Support\ActiveCompany;
use App\Models\NumberingPattern;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpsertNumberingPatternByResourceRequest extends FormRequest
{
    use ValidatesNumberingPatternSegments;

    public function authorize(): bool
    {
        $company = app(ActiveCompany::class)->forUser($this->user());

        if ($company === null) {
            return false;
        }

        $existing = NumberingPattern::query()
            ->where('company_id', $company->id)
            ->where('resource', (string) $this->route('resource'))
            ->first();

        if ($existing !== null) {
            return $this->user()?->can('update', $existing) ?? false;
        }

        return $this->user()?->can('create', NumberingPattern::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareSegmentPayload();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->segmentRules();
    }

    public function withValidator(Validator $validator): void
    {
        $this->withSegmentValidator($validator);
    }
}
