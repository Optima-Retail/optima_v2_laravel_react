<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\NumberingPatterns;

use App\Domain\Config\NumberingPatterns\Enums\NumberingSegmentType;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesNumberingPatternSegments
{
    protected function prepareSegmentPayload(): void
    {
        $segments = $this->input('segments', []);

        if (! is_array($segments)) {
            $segments = [];
        }

        $normalized = [];

        foreach (array_values($segments) as $segment) {
            if (! is_array($segment)) {
                continue;
            }

            $type = (string) ($segment['type'] ?? '');
            $row = ['type' => $type];

            if (in_array($type, [NumberingSegmentType::Letters->value, NumberingSegmentType::Symbols->value], true)) {
                $row['value'] = isset($segment['value']) ? (string) $segment['value'] : '';
            }

            if ($type === NumberingSegmentType::Sequence->value) {
                $row['digit_length'] = filled($segment['digit_length'] ?? null)
                    ? (int) $segment['digit_length']
                    : 5;
            }

            $normalized[] = $row;
        }

        $this->merge([
            'segments' => $normalized,
            'reset_yearly' => $this->boolean('reset_yearly'),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function segmentRules(): array
    {
        return [
            'segments' => ['required', 'array', 'min:1'],
            'segments.*.type' => ['required', 'string', Rule::enum(NumberingSegmentType::class)],
            'segments.*.value' => ['nullable', 'string', 'max:32'],
            'segments.*.digit_length' => ['nullable', 'integer', 'min:1', 'max:10'],
            'reset_yearly' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function withSegmentValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $segments = $this->input('segments', []);

            if (! is_array($segments) || $segments === []) {
                return;
            }

            $hasSequence = false;

            foreach ($segments as $index => $segment) {
                if (! is_array($segment)) {
                    continue;
                }

                $type = NumberingSegmentType::tryFrom((string) ($segment['type'] ?? ''));

                if ($type === null) {
                    continue;
                }

                if ($type === NumberingSegmentType::Sequence) {
                    $hasSequence = true;

                    if (! filled($segment['digit_length'] ?? null)) {
                        $validator->errors()->add(
                            "segments.{$index}.digit_length",
                            'The digit length is required for sequence segments.',
                        );
                    }
                }

                if ($type->requiresValue() && trim((string) ($segment['value'] ?? '')) === '') {
                    $validator->errors()->add(
                        "segments.{$index}.value",
                        'A value is required for this segment type.',
                    );
                }
            }

            if (! $hasSequence) {
                $validator->errors()->add('segments', 'At least one sequence (numbers) segment is required.');
            }
        });
    }
}
