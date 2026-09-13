<?php

declare(strict_types=1);

namespace App\Domain\Contracts\Enums;

enum ContractIterationPeriodicityKind: string
{
    case Basic = 'basic';
    case Complex = 'complex';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
