<?php

declare(strict_types=1);

namespace App\Domain\Companies\Support;

final class Coordinates
{
    /**
     * Normalize a stored decimal coordinate for forms (trim trailing zeros).
     */
    public static function format(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $string = (string) $value;

        if (str_contains($string, '.')) {
            $string = rtrim(rtrim($string, '0'), '.');
        }

        return $string === '' || $string === '-' ? null : $string;
    }
}
