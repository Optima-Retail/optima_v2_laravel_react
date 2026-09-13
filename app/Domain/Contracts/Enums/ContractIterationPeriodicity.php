<?php

declare(strict_types=1);

namespace App\Domain\Contracts\Enums;

enum ContractIterationPeriodicity: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
