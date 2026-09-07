<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\Integrations;

use App\Models\Integration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Integration $integration */
        $integration = $this->route('integration');

        return $this->user()?->can('update', $integration) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge([
                'code' => strtolower(trim((string) $this->input('code'))),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Integration $integration */
        $integration = $this->route('integration');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:64',
                'alpha_dash',
                Rule::unique('integrations', 'code')
                    ->whereNull('deleted_at')
                    ->ignore($integration->id),
            ],
        ];
    }
}
