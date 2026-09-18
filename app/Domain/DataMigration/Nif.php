<?php

declare(strict_types=1);

namespace App\Domain\DataMigration;

final class Nif
{
    /** @var list<string> */
    private const PLACEHOLDERS = ['X', 'FALTA', '#FALTA'];

    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $value = strtoupper(str_replace([' ', '-'], '', trim($raw)));

        if ($value === '' || in_array($value, self::PLACEHOLDERS, true)) {
            return null;
        }

        return $value;
    }

    public static function normalizedName(?string ...$candidates): string
    {
        foreach ($candidates as $candidate) {
            if ($candidate === null) {
                continue;
            }

            $trimmed = strtoupper(trim($candidate));
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return '';
    }
}
