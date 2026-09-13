<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\FormTemplates;

use App\Models\FormTemplate;

final class UpdateFormTemplateRequest extends StoreFormTemplateRequest
{
    public function authorize(): bool
    {
        /** @var FormTemplate|null $template */
        $template = $this->route('form_template');

        return $template instanceof FormTemplate
            && ($this->user()?->can('update', $template) ?? false);
    }
}
