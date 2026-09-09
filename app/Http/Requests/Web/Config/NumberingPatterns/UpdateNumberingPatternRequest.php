<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\NumberingPatterns;

use App\Models\NumberingPattern;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateNumberingPatternRequest extends FormRequest
{
    use ValidatesNumberingPatternSegments;

    public function authorize(): bool
    {
        /** @var NumberingPattern $numberingPattern */
        $numberingPattern = $this->route('numbering_pattern');

        return $this->user()?->can('update', $numberingPattern) ?? false;
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
