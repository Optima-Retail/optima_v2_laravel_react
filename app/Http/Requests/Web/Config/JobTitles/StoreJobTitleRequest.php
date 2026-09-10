<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\JobTitles;

use App\Models\JobTitle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreJobTitleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', JobTitle::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge([
                'code' => strtoupper(trim((string) $this->input('code'))),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('job_titles', 'code')->whereNull('deleted_at'),
            ],
        ];
    }
}
