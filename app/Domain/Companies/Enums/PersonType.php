<?php

declare(strict_types=1);

namespace App\Domain\Companies\Enums;

enum PersonType: string
{
    case Natural = 'F';
    case Legal = 'J';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
