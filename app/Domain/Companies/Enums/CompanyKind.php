<?php

declare(strict_types=1);

namespace App\Domain\Companies\Enums;

enum CompanyKind: string
{
    case OperatingCompany = 'operating_company';
    case Corporation = 'corporation';
    case Holding = 'holding';
    case Ute = 'ute';
    case Party = 'party';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
