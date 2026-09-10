<?php

declare(strict_types=1);

namespace App\Domain\Evaluations\Support;

/**
 * Hardcoded evaluation rating scale (legacy TipoValoracionEnum::stars).
 * Faces / configurable rating_types were removed.
 */
final class RatingScale
{
    public const string CODE = 'stars';

    public const int MAX_SCORE = 5;

    private function __construct() {}
}
