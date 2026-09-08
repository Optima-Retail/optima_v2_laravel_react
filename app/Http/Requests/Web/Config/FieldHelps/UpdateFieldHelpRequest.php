<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\FieldHelps;

use App\Domain\Config\FieldHelps\Services\FieldHelpSchemaService;
use App\Domain\Config\FieldHelps\Services\FieldHelpService;
use App\Models\FieldHelp;
use App\Support\Locale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateFieldHelpRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var FieldHelp $fieldHelp */
        $fieldHelp = $this->route('field_help');

        return $this->user()?->can('update', $fieldHelp) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $table = strtolower(trim((string) $this->input('table', '')));
        $column = strtolower(trim((string) $this->input('column', '')));

        /** @var FieldHelpService $service */
        $service = app(FieldHelpService::class);

        $this->merge([
            'table' => $table,
            'column' => $column,
            'key' => $table !== '' && $column !== '' ? $service->buildKey($table, $column) : null,
            'context' => $table !== '' ? $table : ($this->input('context') ?: null),
            'is_active' => $this->boolean('is_active', true),
            'sort_order' => (int) $this->input('sort_order', 0),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var FieldHelp $fieldHelp */
        $fieldHelp = $this->route('field_help');

        return [
            'table' => ['required', 'string', 'max:191'],
            'column' => ['required', 'string', 'max:191'],
            'key' => [
                'required',
                'string',
                'max:191',
                'regex:/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/',
                Rule::unique('field_helps', 'key')->ignore($fieldHelp->id),
            ],
            'context' => ['nullable', 'string', 'max:191'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'translations' => ['required', 'array', 'min:1'],
            'translations.*.locale' => ['required', 'string', Rule::in(Locale::supported()), 'distinct'],
            'translations.*.title' => ['nullable', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var FieldHelpSchemaService $schema */
            $schema = app(FieldHelpSchemaService::class);

            $table = (string) $this->input('table');
            $column = (string) $this->input('column');

            if ($table !== '' && $column !== '' && ! $schema->isValidTableColumn($table, $column)) {
                $validator->errors()->add('column', __('validation.exists', ['attribute' => 'column']));
            }

            $hasContent = false;
            foreach ((array) $this->input('translations', []) as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $title = trim((string) ($row['title'] ?? ''));
                $description = trim((string) ($row['description'] ?? ''));
                if ($title !== '' || $description !== '') {
                    $hasContent = true;
                    break;
                }
            }

            if (! $hasContent) {
                $validator->errors()->add('translations', 'At least one language needs a title or description.');
            }
        });
    }
}
