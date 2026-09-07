<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\Series;

use App\Models\Series;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateSeriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Series $series */
        $series = $this->route('series');

        return $this->user()?->can('update', $series) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('key')) {
            $this->merge(['key' => strtoupper(trim((string) $this->input('key')))]);
        }

        if ($this->has('color')) {
            $this->merge(['color' => trim((string) $this->input('color'))]);
        }

        if ($this->has('is_selectable')) {
            $this->merge([
                'is_selectable' => filter_var($this->input('is_selectable'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            ]);
        }

        if ($this->has('credit_note_series_id') && $this->input('credit_note_series_id') === '') {
            $this->merge(['credit_note_series_id' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Series $series */
        $series = $this->route('series');

        return [
            'key' => [
                'required',
                'string',
                'max:64',
                'alpha_num',
                Rule::unique('series', 'key')->whereNull('deleted_at')->ignore($series->id),
            ],
            'color' => ['required', 'string', 'max:32'],
            'is_selectable' => ['required', 'boolean'],
            'credit_note_series_id' => ['nullable', 'integer', Rule::exists('series', 'id')->whereNull('deleted_at')],
        ];
    }
}
