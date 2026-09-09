<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\NumberingPatterns;

use App\Domain\Companies\Support\ActiveCompany;
use App\Domain\Config\NumberingPatterns\Enums\NumberingResource;
use App\Models\NumberingPattern;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreNumberingPatternRequest extends FormRequest
{
    use ValidatesNumberingPatternSegments;

    public function authorize(): bool
    {
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
        $companyId = app(ActiveCompany::class)->forUser($this->user())?->id;

        return [
            'resource' => [
                'required',
                'string',
                Rule::enum(NumberingResource::class),
                Rule::unique('numbering_patterns', 'resource')
                    ->where(fn ($query) => $query
                        ->where('company_id', $companyId)
                        ->whereNull('deleted_at')),
            ],
            ...$this->segmentRules(),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->withSegmentValidator($validator);
    }
}
