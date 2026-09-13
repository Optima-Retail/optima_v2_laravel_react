<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Forms;

use App\Models\Form;

final class UpdateFormRequest extends StoreFormRequest
{
    public function authorize(): bool
    {
        /** @var Form|null $form */
        $form = $this->route('form');

        return $form instanceof Form
            && ($this->user()?->can('update', $form) ?? false);
    }
}
