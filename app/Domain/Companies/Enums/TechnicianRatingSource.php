<?php

declare(strict_types=1);

namespace App\Domain\Companies\Enums;

enum TechnicianRatingSource: string
{
    case Optima = 'optima';
    case Customer = 'customer';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
