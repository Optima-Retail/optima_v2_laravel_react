<?php

declare(strict_types=1);

namespace App\Domain\Compliments\Enums;

/**
 * Legacy TipoFelicitacionEnum — IDs preserved for import.
 */
enum ComplimentTypeId: int
{
    case Speed = 1;
    case Kindness = 2;
    case Management = 3;

    public function defaultName(): string
    {
        return match ($this) {
            self::Speed => 'Rapidez',
            self::Kindness => 'Amabilidad',
            self::Management => 'Gestión',
        };
    }
}
