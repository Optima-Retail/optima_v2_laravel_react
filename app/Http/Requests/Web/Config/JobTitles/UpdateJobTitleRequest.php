<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\JobTitles;

use App\Models\JobTitle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateJobTitleRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var JobTitle $jobTitle */
        $jobTitle = $this->route('job_title');

        return $this->user()?->can('update', $jobTitle) ?? false;
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
        /** @var JobTitle $jobTitle */
        $jobTitle = $this->route('job_title');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('job_titles', 'code')
                    ->whereNull('deleted_at')
                    ->ignore($jobTitle->id),
            ],
        ];
    }
}
