<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\TasksToPerform;

use App\Domain\Config\TasksToPerform\Enums\TaskDocumentType;
use App\Models\TaskToPerform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateTaskToPerformRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var TaskToPerform $taskToPerform */
        $taskToPerform = $this->route('task_to_perform');

        return $this->user()?->can('update', $taskToPerform) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => filled($this->input('title')) ? trim((string) $this->input('title')) : null,
            'description' => filled($this->input('description')) ? trim((string) $this->input('description')) : null,
            'is_completed' => $this->boolean('is_completed', false),
            'document_id' => filled($this->input('document_id')) ? $this->integer('document_id') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_completed' => ['required', 'boolean'],
            'document_type' => ['required', 'string', Rule::enum(TaskDocumentType::class)],
            'document_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
