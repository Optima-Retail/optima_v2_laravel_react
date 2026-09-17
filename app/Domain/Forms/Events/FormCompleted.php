<?php

declare(strict_types=1);

namespace App\Domain\Forms\Events;

use App\Models\Form;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired exactly once, the moment a Form's status transitions into one with
 * no further status to advance to — the filling-side counterpart to legacy's
 * Formulario reaching "Terminado".
 */
final class FormCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly Form $form,
    ) {}
}
