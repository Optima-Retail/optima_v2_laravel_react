<?php

declare(strict_types=1);

namespace App\Domain\Companies\Enums;

enum CompanyRelationshipKind: string
{
    case Customer = 'customer';
    case Supplier = 'supplier';
    case Technician = 'technician';
    case Partner = 'partner';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
